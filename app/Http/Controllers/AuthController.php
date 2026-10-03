<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Notifications\ReviewerNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    public function __construct(protected ReviewerNotifier $notifier) {}

    public function showLogin(): Response|RedirectResponse
    {
        if (Auth::check()) {
            return $this->homeFor(request()->user());
        }

        return Inertia::render('Login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'Email atau password salah.',
            ]);
        }

        $request->session()->regenerate();

        $user = $request->user();

        // Registrasi DITOLAK → tidak boleh masuk (pending boleh — fitur VPS terkunci).
        if ($user->status === User::STATUS_REJECTED) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'Registrasi Anda ditolak oleh admin. Silakan hubungi admin untuk informasi.',
            ]);
        }

        // Tujuan awal (mis. /admin saat belum login) hanya untuk reviewer;
        // user biasa selalu jatuh ke /dashboard (guard role di middleware).
        $intended = session()->get('url.intended');

        if ($user->canReview() && is_string($intended) && $intended !== '') {
            session()->forget('url.intended');

            return redirect()->to($intended);
        }

        return $this->homeFor($user);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    // ── Registrasi user publik ──────────────────────────────────────────────

    public function showRegister(): Response|RedirectResponse
    {
        if (Auth::check()) {
            return $this->homeFor(request()->user());
        }

        return Inertia::render('Register');
    }

    /**
     * Buat akun baru berstatus `pending` — aktif setelah validasi admin/operator.
     */
    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'email.unique' => 'Email sudah terdaftar. Silakan login.',
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'], // cast `hashed`
            'role' => User::ROLE_USER,
            'status' => User::STATUS_PENDING,
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        // Beri tahu admin/operator lewat lonceng notifikasi.
        $this->notifier->registrationReceived($user);

        return redirect()
            ->route('dashboard')
            ->with('success', 'Registrasi berhasil. Akun Anda menunggu validasi admin — fitur Request VPS terbuka setelah disetujui.');
    }

    /**
     * Admin → /admin (atau halaman review untuk operator), user → /dashboard.
     */
    protected function homeFor($user): RedirectResponse
    {
        if ($user->isAdmin()) {
            return redirect()->to(route('admin.dashboard'));
        }

        if ($user->isOperator()) {
            return redirect()->to(route('admin.registrations.index'));
        }

        return redirect()->to(route('dashboard'));
    }
}

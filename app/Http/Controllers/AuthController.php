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

        // 1. Registrasi DITOLAK → tidak boleh masuk (permanen).
        if ($user->status === User::STATUS_REJECTED) {
            $this->flushSession();

            throw ValidationException::withMessages([
                'email' => 'Registrasi Anda ditolak oleh admin. Silakan hubungi admin untuk informasi.',
            ]);
        }

        // 2. Email BELUM diverifikasi → tidak boleh login
        //    (tautan verifikasi bisa dikirim ulang di halaman verifikasi).
        if (! $user->hasVerifiedEmail()) {
            $this->flushSession();

            throw ValidationException::withMessages([
                'email' => 'Email Anda belum diverifikasi. Cek inbox email untuk tautan verifikasi — bisa minta kirim ulang di halaman verifikasi.',
            ]);
        }

        // Catatan: status PENDING tetap BISA login — hanya fitur Request VPS
        // yang terkuncil sampai divalidasi admin/operator (canRequestVps()).

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
     * Buat akun baru: status `pending` + email BELUM diverifikasi.
     * Keduanya (email + admin) wajib sebelum bisa login.
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

        // Email verifikasi (signed URL 60 menit) + lonceng untuk admin/operator.
        // Gagal kirim (SMTP down dsb.) TIDAK menghapus akun — user bisa kirim ulang.
        try {
            $user->sendEmailVerificationNotification();
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('verification.notice')->with(
                'error',
                'Registrasi berhasil, tetapi email verifikasi gagal dikirim. '
                .'Gunakan tombol "Kirim ulang" di halaman verifikasi atau hubungi admin.'
            );
        }

        $this->notifier->registrationReceived($user);

        return redirect()
            ->route('verification.notice')
            ->with('success', 'Registrasi berhasil. Kami sudah mengirim tautan verifikasi ke '.$user->email
                .' — klik tautan di email, lalu Anda bisa login. Fitur Request VPS terbuka '
                .'setelah divalidasi admin/operator.');
    }

    // ── Verifikasi email (tanpa login — dibuktikan lewat link signed) ───────

    /**
     * Halaman "cek email Anda untuk tautan verifikasi" + kirim ulang.
     */
    public function showVerifyNotice(): Response|RedirectResponse
    {
        if (Auth::check()) {
            return $this->homeFor(request()->user());
        }

        return Inertia::render('VerifyEmail');
    }

    /**
     * Kirim ulang tautan verifikasi (throttled, pesan generik anti-enumerasi).
     */
    public function resendVerification(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if ($user !== null && ! $user->hasVerifiedEmail()) {
            try {
                $user->sendEmailVerificationNotification();
            } catch (\Throwable $e) {
                report($e);

                return back()->with(
                    'error',
                    'Email verifikasi gagal dikirim (layanan email bermasalah). Coba lagi nanti.'
                );
            }
        }

        return back()->with(
            'success',
            'Jika email terdaftar dan belum diverifikasi, tautan verifikasi baru sudah dikirim. '
            .'Periksa kotak masuk dan folder spam.'
        );
    }

    /**
     * Endpoint tujuan link di email — route `signed` (HMAC) + `hash = sha1(email)`.
     * TANPA auth: kepemilikan email dibuktikan oleh link yang diterima.
     */
    public function verifyEmail(Request $request, string $id, string $hash): RedirectResponse
    {
        $user = User::find($id);

        if ($user === null || ! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            abort(403, 'Tautan verifikasi tidak valid.');
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        $message = $user->status === User::STATUS_APPROVED
            ? 'Email berhasil diverifikasi — silakan login.'
            : 'Email berhasil diverifikasi — silakan login. Fitur Request VPS terbuka '
              .'setelah akun divalidasi admin/operator.';

        return redirect()->route('login')->with('success', $message);
    }

    /** Lepas sesi (logout + regenerasi token) — dipakai saat login ditolak. */
    protected function flushSession(): void
    {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
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

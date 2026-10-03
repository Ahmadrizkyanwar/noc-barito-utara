<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
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

        // Tujuan awal (mis. /admin saat belum login) hanya untuk admin;
        // user biasa selalu jatuh ke /dashboard (guard role di middleware).
        $intended = session()->get('url.intended');

        if ($user->isAdmin() && is_string($intended) && $intended !== '') {
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

    /**
     * Admin → /admin, user → /dashboard.
     */
    protected function homeFor($user): RedirectResponse
    {
        return redirect()->to($user->isAdmin() ? route('admin.dashboard') : route('dashboard'));
    }
}

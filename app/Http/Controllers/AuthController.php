<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLoginForm(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withErrors(['email' => 'Email atau password salah.'])
                ->onlyInput('email');
        }

        $user = Auth::user();

        // Tolak akun yang dinonaktifkan owner (soft-deactivate).
        if (! $user->isActive()) {
            Auth::logout();
            return back()
                ->withErrors(['email' => 'Akun ini nonaktif. Hubungi pemilik (owner).'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->route($user->defaultRouteName())
            ->with('success', 'Selamat datang kembali, ' . $user->name . '.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
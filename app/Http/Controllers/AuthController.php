<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'password.required' => 'Password wajib diisi.',
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            // Redirect berdasarkan peran (Role Redirection)
            $user = Auth::user();
            if ($user->role === 'owner') {
                return redirect()->intended(route('dashboard'));
            } elseif ($user->role === 'staff') {
                return redirect()->intended(route('sales.create'));
            }

            return redirect()->intended(route('home'));
        }

        // Return pesan kesalahan yang jelas jika autentikasi gagal
        throw ValidationException::withMessages([
            'email' => 'Kombinasi email dan kata sandi tidak cocok dengan data kami.',
        ]);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Anda telah berhasil keluar dari akun.');
    }
}

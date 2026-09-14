<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        // 1. Jalankan proses autentikasi bawaan Laravel Breeze
        $request->authenticate();

        // 2. Regenerasi session id untuk keamanan
        $request->session()->regenerate();

        $user = Auth::user();

        // 3. Pengarahan halaman (Redirect) berdasarkan Role User
        if ($user->role === 'penjual') {
            // Jika akun ber-role Penjual -> Diarahkan ke Dashboard Penjual
            return redirect()->intended(route('dashboard', absolute: false))
                ->with('success', 'Selamat datang kembali di Dashboard Penjual!');
        }

        // Jika akun ber-role Pembeli -> Diarahkan ke Katalog Utama (welcome.blade.php)
        return redirect()->intended(route('home', absolute: false))
            ->with('success', 'Berhasil masuk! Silakan lanjutkan booking barang favoritmu.');
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}

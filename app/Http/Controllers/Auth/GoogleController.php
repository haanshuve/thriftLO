<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleController extends Controller
{
    // Arahkan user ke halaman persetujuan akun Google
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    // Google mengarahkan kembali ke sini setelah user memilih akun
    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            Log::warning('Login Google gagal: ' . $e->getMessage());

            return redirect()->route('login')->with('error', 'Login dengan Google gagal atau dibatalkan. Silakan coba lagi.');
        }

        $email = Str::lower((string) $googleUser->getEmail());

        // Email hanya boleh dipakai untuk mencocokkan akun kalau sudah diverifikasi Google
        $raw = $googleUser->getRaw();
        $emailVerified = filter_var($raw['email_verified'] ?? $raw['verified_email'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($email === '' || !$emailVerified) {
            return redirect()->route('login')->with('error', 'Email akun Google kamu belum terverifikasi, jadi tidak bisa dipakai untuk masuk.');
        }

        // 1. Sudah pernah login dengan Google sebelumnya
        $user = User::where('google_id', $googleUser->getId())->first();

        if (!$user) {
            $user = User::where('email', $email)->first();

            if ($user) {
                // 2. Email sudah terdaftar manual: hubungkan ke akun yang ada, jangan buat duplikat
                if ($user->google_id && $user->google_id !== $googleUser->getId()) {
                    return redirect()->route('login')->with('error', 'Email ini sudah terhubung dengan akun Google lain.');
                }

                $user->google_id = $googleUser->getId();
                $user->email_verified_at ??= now();
                $user->save();
            } else {
                // 3. Pengguna baru: buat akun pembeli. Fitur penjual tetap lewat registrasi + verifikasi KYC manual
                $user = User::create([
                    'name'          => $googleUser->getName() ?: Str::before($email, '@'),
                    'email'         => $email,
                    'google_id'     => $googleUser->getId(),
                    'password'      => Hash::make(Str::random(40)),
                    'role'          => 'pembeli',
                    'seller_status' => 'verified',
                ]);
                $user->forceFill(['email_verified_at' => now()])->save();
            }
        }

        Auth::login($user, remember: true);
        request()->session()->regenerate();

        return redirect()->intended(route('home', absolute: false))
            ->with('success', 'Berhasil masuk dengan Google. Selamat datang, ' . $user->name . '!');
    }
}

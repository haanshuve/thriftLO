<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'phone_number' => ['required', 'string', 'max:20'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'role' => ['required', 'in:pembeli,penjual'],
        ];

        // Jika mendaftar sebagai penjual, wajib validasi KYC & atribut toko
        if ($request->role === 'penjual') {
            $rules['nama_toko'] = ['required', 'string', 'max:255'];
            $rules['lokasi_lapak'] = ['required', 'string', 'max:255'];
            $rules['ktp_photo'] = ['required', 'image', 'mimes:jpeg,png,jpg', 'max:2048'];
            $rules['selfie_ktp'] = ['required', 'image', 'mimes:jpeg,png,jpg', 'max:2048'];
        }

        $request->validate($rules);

        // Proses simpan file KTP dan Selfie jika penjual
        $ktpPath = null;
        $selfiePath = null;

        if ($request->role === 'penjual') {
            if ($request->hasFile('ktp_photo')) {
                $ktpPath = $request->file('ktp_photo')->store('kyc-ktp', 'public');
            }
            if ($request->hasFile('selfie_ktp')) {
                $selfiePath = $request->file('selfie_ktp')->store('kyc-selfie', 'public');
            }
        }

        $user = User::create([
            'name' => $request->name,
            'phone_number' => $request->phone_number,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'nama_toko' => $request->role === 'penjual' ? $request->nama_toko : null,
            'lokasi_lapak' => $request->role === 'penjual' ? $request->lokasi_lapak : null,
            'ktp_number' => $request->role === 'penjual' ? 'VERIFIED-KYC' : null, // Mengisi placeholder ktp_number
            'selfie_path' => $selfiePath,
            'seller_status' => $request->role === 'penjual' ? 'pending' : 'verified',
        ]);

        event(new Registered($user));

        Auth::login($user);

        if ($user->role === 'penjual') {
            return redirect(route('dashboard', absolute: false))->with('success', 'Akun penjual berhasil didaftarkan! Status KYC sedang ditinjau.');
        }

        return redirect(route('home', absolute: false))->with('success', 'Akun pembeli berhasil didaftarkan!');
    }
}

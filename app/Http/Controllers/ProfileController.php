<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Support\SellerLocation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    // Unggah atau ganti foto profil; selalu milik user yang sedang login
    public function updateAvatar(Request $request): RedirectResponse
    {
        $request->validateWithBag('avatar', [
            'avatar' => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
        ], [
            'avatar.required' => 'Pilih foto yang mau diunggah.',
            'avatar.uploaded' => 'Foto gagal diunggah. Pastikan ukurannya tidak lebih dari 2MB lalu coba lagi.',
            'avatar.image'    => 'Foto profil harus berupa gambar (JPG, PNG, atau WEBP).',
            'avatar.mimes'    => 'Foto profil harus berformat JPG, PNG, atau WEBP.',
            'avatar.max'      => 'Ukuran foto profil maksimal 2MB.',
        ]);

        $user = $request->user();
        $oldPath = $user->avatar_path;

        $user->update(['avatar_path' => $request->file('avatar')->store('avatars', 'public')]);

        if ($oldPath) {
            Storage::disk('public')->delete($oldPath);
        }

        return Redirect::route('profile.edit')->with('status', 'avatar-updated');
    }

    // Titik COD penjual Batam: patokan alamat + pin peta, ditampilkan ke pembeli di detail produk & tiket booking
    public function updateCodPoint(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->role === 'penjual', 403);

        if (!$user->isInBatam()) {
            return Redirect::route('profile.edit')->withErrors(
                ['cod_address' => 'Titik COD hanya untuk lapak di Batam. Ubah lokasi lapak ke kawasan Batam dulu.'],
                'codPoint'
            );
        }

        $max = (int) config('thriftlo.cod_point.address_max');

        // Spasi di awal/akhir tidak dihitung, sama seperti penghitung karakter di form
        $request->merge(['cod_address' => trim((string) $request->input('cod_address'))]);

        $validator = Validator::make($request->all(), [
            'cod_address'   => ['required', 'string', 'max:' . $max],
            'cod_latitude'  => ['required', 'numeric', 'between:-90,90'],
            'cod_longitude' => ['required', 'numeric', 'between:-180,180'],
        ], [
            'cod_address.required'   => 'Tulis patokan alamat COD, mis. "Depan Mega Mall, pintu utama".',
            'cod_address.max'        => "Patokan alamat COD maksimal {$max} karakter.",
            'cod_latitude.required'  => 'Pasang pin lokasi COD di peta dulu.',
            'cod_latitude.numeric'   => 'Latitude harus berupa angka.',
            'cod_latitude.between'   => 'Latitude harus di antara -90 dan 90.',
            'cod_longitude.required' => 'Pasang pin lokasi COD di peta dulu.',
            'cod_longitude.numeric'  => 'Longitude harus berupa angka.',
            'cod_longitude.between'  => 'Longitude harus di antara -180 dan 180.',
        ]);

        // Koordinat yang sudah valid harus berada di wilayah Batam (kotak di config thriftlo.cod_point.areas)
        $validator->after(function ($validator) use ($request) {
            if ($validator->errors()->hasAny(['cod_latitude', 'cod_longitude'])) {
                return;
            }

            if (!SellerLocation::isInBatamArea((float) $request->input('cod_latitude'), (float) $request->input('cod_longitude'))) {
                $validator->errors()->add('cod_latitude', 'Titik COD harus berada di wilayah Batam.');
            }
        });

        $data = $validator->validateWithBag('codPoint');

        $user->update([
            'cod_address'   => $data['cod_address'],
            'cod_latitude'  => round((float) $data['cod_latitude'], 7),
            'cod_longitude' => round((float) $data['cod_longitude'], 7),
        ]);

        return Redirect::to(route('profile.edit') . '#titik-cod')->with('status', 'cod-point-updated');
    }

    public function destroyCodPoint(Request $request): RedirectResponse
    {
        abort_unless($request->user()->role === 'penjual', 403);

        $request->user()->update(['cod_address' => null, 'cod_latitude' => null, 'cod_longitude' => null]);

        return Redirect::to(route('profile.edit') . '#titik-cod')->with('status', 'cod-point-deleted');
    }

    public function destroyAvatar(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->update(['avatar_path' => null]);
        }

        return Redirect::route('profile.edit')->with('status', 'avatar-deleted');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}

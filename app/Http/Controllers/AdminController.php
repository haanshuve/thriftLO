<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminController extends Controller
{
    // Menampilkan Daftar Penjual yang Menunggu Verifikasi KYC
    public function index()
    {
        // Ambil semua user dengan role penjual
        $pendingSellers = User::where('role', 'penjual')->get();
        return view('admin.sellers', compact('pendingSellers'));
    }

    // Aksi untuk Menyetujui (Verify) Penjual
    public function verifySeller($id)
    {
        $seller = User::findOrFail($id);
        $seller->update(['seller_status' => 'verified']);

        return redirect()->back()->with('success', 'Akun penjual ' . $seller->name . ' berhasil diverifikasi!');
    }

    // Menampilkan foto KTP penjual dari disk privat (khusus admin)
    public function showKtp($id)
    {
        return $this->privateDocument(User::findOrFail($id)->ktp_photo_path, 'Foto KTP tidak ditemukan.');
    }

    // Menampilkan foto selfie KYC penjual dari disk privat (khusus admin)
    public function showSelfie($id)
    {
        return $this->privateDocument(User::findOrFail($id)->selfie_path, 'Foto selfie tidak ditemukan.');
    }

    private function privateDocument(?string $path, string $notFoundMessage)
    {
        if (!$path || !Storage::disk('local')->exists($path)) {
            abort(404, $notFoundMessage);
        }

        return Storage::disk('local')->response($path);
    }

    // Aksi untuk Menolak Penjual
    public function rejectSeller($id)
    {
        $seller = User::findOrFail($id);
        $seller->update(['seller_status' => 'rejected']);

        return redirect()->back()->with('success', 'Akun penjual ditolak.');
    }
}

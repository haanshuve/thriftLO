<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

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

    // Aksi untuk Menolak Penjual
    public function rejectSeller($id)
    {
        $seller = User::findOrFail($id);
        $seller->update(['seller_status' => 'rejected']);

        return redirect()->back()->with('success', 'Akun penjual ditolak.');
    }
}

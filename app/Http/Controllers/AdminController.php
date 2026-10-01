<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminController extends Controller
{
    private const STATUSES = ['pending', 'verified', 'rejected'];

    // Menampilkan daftar penjual per status KYC (default: yang menunggu verifikasi)
    public function index(Request $request)
    {
        $status = in_array($request->query('status'), self::STATUSES, true) ? $request->query('status') : 'pending';

        $counts = User::where('role', 'penjual')
            ->selectRaw('seller_status, COUNT(*) as total')
            ->groupBy('seller_status')
            ->pluck('total', 'seller_status');

        // Antrean menunggu: yang paling lama mendaftar di atas
        $sellers = User::where('role', 'penjual')
            ->where('seller_status', $status)
            ->when($status === 'pending', fn ($q) => $q->oldest(), fn ($q) => $q->latest('updated_at'))
            ->get();

        return view('admin.sellers', compact('sellers', 'status', 'counts'));
    }

    // Aksi untuk Menyetujui (Verify) Penjual
    public function verifySeller($id)
    {
        $seller = $this->findSeller($id);
        $seller->update(['seller_status' => 'verified']);

        return redirect()->back()
            ->with('success', 'Penjual ' . ($seller->nama_toko ?: $seller->name) . ' berhasil diverifikasi dan sudah bisa berjualan.');
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

    // Aksi untuk Menolak Penjual (juga dipakai untuk mencabut verifikasi)
    public function rejectSeller($id)
    {
        $seller = $this->findSeller($id);
        $seller->update(['seller_status' => 'rejected']);

        return redirect()->back()
            ->with('success', 'Verifikasi penjual ' . ($seller->nama_toko ?: $seller->name) . ' ditolak.');
    }

    // Aksi verifikasi hanya berlaku untuk akun penjual, bukan pembeli atau admin
    private function findSeller($id): User
    {
        return User::where('role', 'penjual')->findOrFail($id);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\ProductRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class RequestController extends Controller
{
    // Menampilkan request barang: semua yang masih dicari, atau milik user sendiri
    public function index(Request $request)
    {
        $tab = $request->query('tab') === 'saya' ? 'saya' : 'semua';

        $requests = ProductRequest::with('user')
            ->when($tab === 'saya',
                fn ($q) => $q->where('user_id', Auth::id()),
                fn ($q) => $q->where('status', 'Open'))
            ->orderByRaw("CASE WHEN status = 'Open' THEN 0 ELSE 1 END")
            ->latest()
            ->get();

        $categories = config('thriftlo.categories');

        return view('requests.index', compact('requests', 'tab', 'categories'));
    }

    // Menyimpan request baru yang dibuat oleh pembeli
    public function store(Request $request)
    {
        $request->validateWithBag('request', [
            'nama_barang'     => 'required|string|max:255',
            'kategori'        => ['required', Rule::in(array_keys(config('thriftlo.categories')))],
            'budget_maksimal' => 'required|numeric|min:0|max:1000000000',
            'lokasi_cod'      => 'required|string|max:255',
            'deskripsi'       => 'required|string|max:2000',
        ], [
            'nama_barang.required'     => 'Nama barang yang dicari wajib diisi.',
            'kategori.in'              => 'Pilih kategori dari daftar yang tersedia.',
            'budget_maksimal.required' => 'Budget maksimal wajib diisi.',
            'budget_maksimal.numeric'  => 'Budget harus berupa angka.',
            'budget_maksimal.min'      => 'Budget tidak boleh negatif.',
            'lokasi_cod.required'      => 'Lokasi COD wajib diisi.',
            'deskripsi.required'       => 'Jelaskan kriteria barang yang kamu cari.',
        ]);

        ProductRequest::create([
            'user_id'         => Auth::id(),
            'nama_barang'     => $request->nama_barang,
            'kategori'        => $request->kategori,
            'budget_maksimal' => $request->budget_maksimal,
            'lokasi_cod'      => $request->lokasi_cod,
            'deskripsi'       => $request->deskripsi,
            'status'          => 'Open',
        ]);

        return redirect()->route('requests.index', ['tab' => 'saya'])
            ->with('success', 'Request barang berhasil diposting! Penjual di Batam akan segera melihatnya.');
    }

    // Pemilik request menandai barangnya sudah didapat, request tidak tampil lagi di daftar
    public function fulfill(ProductRequest $productRequest)
    {
        abort_unless((int) $productRequest->user_id === (int) Auth::id(), 403);

        $productRequest->update(['status' => 'Fulfilled']);

        return redirect()->route('requests.index', ['tab' => 'saya'])
            ->with('success', 'Request "' . $productRequest->nama_barang . '" ditandai sudah didapat.');
    }
}

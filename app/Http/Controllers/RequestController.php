<?php

namespace App\Http\Controllers;

use App\Models\ProductRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;


class RequestController extends Controller
{
    // Menampilkan daftar semua request barang dari pembeli
    public function index()
    {
        $requests = ProductRequest::with('user')->latest()->get();
        return view('requests.index', compact('requests'));
    }

    // Menyimpan request baru yang dibuat oleh pembeli
    public function store(Request $request)
    {
        if (!Auth::check()) {
            return redirect()->route('login')->with('error', 'Silakan login terlebih dahulu untuk membuat request!');
        }

        $request->validate([
            'nama_barang'     => 'required|string|max:255',
            'kategori'        => 'required|string',
            'budget_maksimal' => 'required|numeric',
            'lokasi_cod'      => 'required|string|max:255',
            'deskripsi'       => 'required|string',
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

        return redirect()->route('requests.index')->with('success', 'Request barang berhasil diposting! Penjual di Batam akan segera melihatnya.');
    }
}

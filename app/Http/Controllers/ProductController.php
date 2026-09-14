<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with('user')->whereIn('status', ['Available', 'Booked']);

        if ($request->has('mode') && $request->mode != 'all') {
            $query->where('mode_jual', $request->mode);
        }

        if ($request->has('kategori') && $request->kategori != 'all') {
            $query->where('kategori', $request->kategori);
        }

        $products = $query->latest()->get();

        $totalItems = Product::count();
        $totalBookings = Booking::count();
        $wastePreventedKg = ($totalItems * 0.5) + ($totalBookings * 1.2);

        return view('welcome', compact('products', 'wastePreventedKg', 'totalBookings'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nama_barang' => 'required|string|max:255',
            'mode_jual'   => 'required|string',
            'kategori'    => 'required|string',
            'harga'       => 'required|numeric',
            'grade'       => 'required|string',
            'image'       => 'required|image|mimes:jpeg,png,jpg,webp|max:2048', // Validasi file gambar asli (Maks 2MB)
            'deskripsi'   => 'nullable|string',
        ]);

        // Simpan file gambar secara fisik ke folder storage/app/public/products
        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('products', 'public');
        }

        Product::create([
            'user_id'     => auth()->id(),
            'title'       => $request->nama_barang,
            'mode_jual'   => $request->mode_jual,
            'kategori'    => $request->kategori,
            'price'       => $request->harga,
            'grade'       => $request->grade,
            'image_url'   => $imagePath, // Menyimpan path file storage
            'image_path'  => $imagePath, // Menyimpan path file storage
            'video_proof' => $request->video_proof_url,
            'description' => $request->deskripsi,
            'status'      => 'Available',
        ]);

        return redirect()->back()->with('success', 'Barang preloved berhasil ditayangkan!');
    }

    public function bookProduct(Request $request, $id)
    {
        if (!Auth::check()) {
            return redirect()->route('login')->with('error', 'Silakan login terlebih dahulu untuk melakukan booking barang!');
        }

        $request->validate([
            'lokasi_cod' => 'required|string|max:255',
            'waktu_cod'  => 'required',
        ]);

        $product = Product::findOrFail($id);

        if ($product->status !== 'Available') {
            return redirect()->back()->with('error', 'Maaf, barang ini sudah di-booking oleh pembeli lain!');
        }

        $qrToken = 'TL-' . strtoupper(Str::random(8));

        Booking::create([
            'product_id'    => $product->id,
            'user_id'       => Auth::id(),
            'qr_code_token' => $qrToken,
            'lokasi_cod'    => $request->lokasi_cod,
            'waktu_cod'     => $request->waktu_cod,
            'status_cod'    => 'Pending',
        ]);

        $product->update(['status' => 'Booked']);

        return redirect()->route('bookings.index')->with('success', 'Barang berhasil di-booking! Token QR COD kamu: ' . $qrToken);
    }

    public function sellerDashboard()
    {
        $user = Auth::user();
        $myProducts = Product::where('user_id', $user->id)->latest()->get();
        $myBookings = Booking::with(['product', 'user'])
            ->whereHas('product', function($q) use ($user) {
                $q->where('user_id', $user->id);
            })->latest()->get();

        return view('dashboard', compact('myProducts', 'myBookings'));
    }

    public function verifyQrCode(Request $request)
    {
        $request->validate([
            'qr_code_token' => 'required|string',
        ]);

        $cleanToken = strtoupper(trim($request->qr_code_token));
        $booking = Booking::where('qr_code_token', $cleanToken)->first();

        if (!$booking) {
            return redirect()->back()->with('error', 'Token QR tidak ditemukan atau tidak valid!');
        }

        if ($booking->status_cod === 'Completed') {
            return redirect()->back()->with('error', 'Token QR ini sudah pernah diverifikasi sebelumnya!');
        }

        $booking->update(['status_cod' => 'Completed']);
        $booking->product->update(['status' => 'Sold Out']);

        return redirect()->back()->with('success', 'Transaksi COD Sukses! Status barang berubah menjadi Sold Out.');
    }

    public function destroy($id)
    {
        $product = Product::where('id', $id)->where('user_id', Auth::id())->firstOrFail();

        // Hapus file gambar fisik dari storage jika ada
        if ($product->image_url && !filter_var($product->image_url, FILTER_VALIDATE_URL)) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($product->image_url);
        }

        $product->delete();

        return redirect()->back()->with('success', 'Barang preloved berhasil dihapus dari inventaris!');
    }
}

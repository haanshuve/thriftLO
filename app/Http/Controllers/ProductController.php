<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        // Produk di atas kuota gratis milik penjual tanpa langganan tidak ditampilkan
        $query = Product::with('user')->visibleInCatalog();

        // Fitur Pencarian (Search) berdasarkan Judul atau Deskripsi Barang
        if ($request->has('search') && !empty($request->search)) {
            $keyword = $request->search;
            $query->where(function($q) use ($keyword) {
                $q->where('title', 'like', '%' . $keyword . '%')
                  ->orWhere('description', 'like', '%' . $keyword . '%');
            });
        }

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

        $categories = config('thriftlo.categories');

        return view('welcome', compact('products', 'wastePreventedKg', 'totalBookings', 'categories'));
    }

    public function store(Request $request)
    {
        // Form di dashboard dikunci untuk penjual yang belum lolos KYC; cek juga di server
        $user = Auth::user();
        if ($user->role !== 'penjual' || $user->seller_status !== 'verified') {
            return redirect()->route('dashboard')->with('error', 'Hanya penjual terverifikasi yang bisa menayangkan barang.');
        }

        if (!$user->canListMoreProducts()) {
            return redirect()->route('subscription.show')->with('limit_reached', true);
        }

        $request->validateWithBag('product', [
            'nama_barang'     => 'required|string|max:255',
            'mode_jual'       => ['required', Rule::in(['ecer', 'borongan'])],
            'kategori'        => ['required', Rule::in(array_keys(config('thriftlo.categories')))],
            'harga'           => 'required|numeric|min:0',
            'grade'           => ['required', Rule::in(config('thriftlo.grades'))],
            // Foto kamera HP umumnya 3-6MB, jadi batasnya 5MB
            'image'           => 'required|image|mimes:jpeg,png,jpg,webp|max:5120',
            'video_proof_url' => 'nullable|url|max:500',
            'deskripsi'       => 'nullable|string|max:2000',
        ], [
            'nama_barang.required' => 'Nama barang wajib diisi.',
            'kategori.in'          => 'Pilih kategori dari daftar yang tersedia.',
            'grade.in'             => 'Pilih grade kondisi dari daftar yang tersedia.',
            'harga.required'       => 'Harga wajib diisi.',
            'harga.numeric'        => 'Harga harus berupa angka.',
            'harga.min'            => 'Harga tidak boleh negatif.',
            'image.required'       => 'Foto produk wajib diunggah.',
            'image.image'          => 'Foto produk harus berupa gambar (JPG, PNG, atau WEBP).',
            'image.mimes'          => 'Foto produk harus berformat JPG, PNG, atau WEBP.',
            'image.max'            => 'Ukuran foto produk maksimal 5MB.',
            'video_proof_url.url'  => 'Link video proof harus berupa URL yang valid (diawali https://).',
        ]);

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
            'image_url'   => $imagePath,
            'image_path'  => $imagePath,
            'video_proof' => $request->video_proof_url,
            'description' => $request->deskripsi,
            'status'      => 'Available',
        ]);

        return redirect()->back()->with('success', 'Barang preloved berhasil ditayangkan!');
    }

    public function bookProduct(Request $request, $id)
    {
        if (!Auth::check()) {
            return redirect()->route('login')->with('error', 'Silakan login terlebih dahulu!');
        }

        $request->validate([
            'lokasi_cod' => 'required|string|max:255',
            'waktu_cod'  => 'required',
        ]);

        $product = Product::findOrFail($id);

        if (strtolower($product->status) !== 'available') {
            return redirect()->back()->with('error', 'Yah, barang ini keburu di-booking orang lain. Cek barang lain yang mirip, yuk!');
        }

        // Barang yang disembunyikan karena kuota penjual habis tidak bisa di-booking lewat URL langsung
        if (!Product::visibleInCatalog()->whereKey($product->id)->exists()) {
            return redirect()->back()->with('error', 'Barang ini sedang tidak ditayangkan penjualnya.');
        }

        $qrToken = 'TL-' . strtoupper(Str::random(8));

        // Menyimpan booking dengan struktur kolom database cod_location & cod_schedule
        Booking::create([
            'product_id'   => $product->id,
            'user_id'      => Auth::id(),
            'qr_token'     => $qrToken,
            'cod_location' => $request->lokasi_cod,
            'cod_schedule' => $request->waktu_cod,
            'status_cod'   => 'Pending',
        ]);

        $product->update(['status' => 'Booked']);

        return redirect()->route('bookings.index')->with('success', 'Mantap! Barangnya udah dikunci buat kamu. Tinggal ketemuan sama penjualnya dan tunjukin token ' . $qrToken . ' di bawah ini.');
    }

    public function sellerDashboard()
    {
        $user = Auth::user();
        $myProducts = Product::where('user_id', $user->id)->latest()->get();
        $myBookings = Booking::with(['product', 'user'])
            ->whereHas('product', function($q) use ($user) {
                $q->where('user_id', $user->id);
            })->latest()->get();

        // Produk aktif di atas kuota gratis yang sedang disembunyikan dari katalog
        $hiddenProductIds = $user->hasActiveSubscription()
            ? []
            : $myProducts->whereIn('status', Product::ACTIVE_STATUSES)->sortBy('id')
                ->slice((int) config('thriftlo.subscription.free_product_limit'))->pluck('id')->all();

        return view('dashboard', compact('myProducts', 'myBookings', 'hiddenProductIds'));
    }

    public function verifyQrCode(Request $request)
    {
        $request->validate([
            'qr_code_token' => 'required|string',
        ]);

        $cleanToken = strtoupper(trim($request->qr_code_token));
        // Hanya booking untuk barang milik penjual yang sedang login
        $booking = Booking::where('qr_token', $cleanToken)
            ->whereHas('product', function ($q) {
                $q->where('user_id', Auth::id());
            })->first();

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

        if ($product->image_url && !filter_var($product->image_url, FILTER_VALIDATE_URL)) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($product->image_url);
        }

        $product->delete();

        return redirect()->back()->with('success', 'Barang preloved berhasil dihapus dari inventaris!');
    }
}

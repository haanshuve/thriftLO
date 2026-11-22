<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Booking;
use App\Models\Order;
use App\Models\User;
use App\Payments\BookingFeeGateway;
use App\Payments\PaymentException;
use App\Support\CodSpots;
use App\Support\FormToken;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        // Produk di atas kuota gratis milik penjual tanpa langganan tidak ditampilkan
        $query = Product::with(['user', 'brand'])->visibleInCatalog();

        // Fitur Pencarian (Search) berdasarkan Judul, Deskripsi, atau Merek Barang
        if ($request->has('search') && !empty($request->search)) {
            $keyword = $request->search;
            $query->where(function($q) use ($keyword) {
                $q->where('title', 'like', '%' . $keyword . '%')
                  ->orWhere('description', 'like', '%' . $keyword . '%')
                  ->orWhere('brand_other', 'like', '%' . $keyword . '%')
                  ->orWhereHas('brand', fn ($b) => $b->where('name', 'like', '%' . $keyword . '%'));
            });
        }

        // Filter merek (?merek=slug), bisa digabung dengan pencarian dan kategori
        $activeBrand = $request->filled('merek') ? Brand::active()->where('slug', $request->merek)->first() : null;
        if ($request->filled('merek')) {
            $query->where('brand_id', $activeBrand?->id ?? 0);
        }

        if ($request->has('mode') && $request->mode != 'all') {
            $query->where('mode_jual', $request->mode);
        }

        if ($request->has('kategori') && $request->kategori != 'all') {
            $query->where('kategori', $request->kategori);
        }

        $products = $query->latest()->get();

        // Koleksi bertema (?koleksi=...), tujuan tautan "Lihat semua" di beranda. Tambahan saja:
        // disaring dari hasil query di atas, jadi filter lain tetap berlaku seperti biasa.
        $collection = array_key_exists((string) $request->query('koleksi'), self::COLLECTIONS) ? $request->query('koleksi') : null;
        if ($collection) {
            $products = $products->filter(fn (Product $p) => self::inCollection($p, $collection))->values();
        }

        $isFiltered = $request->filled('search')
            || ($request->filled('kategori') && $request->kategori !== 'all')
            || $request->filled('merek')
            || $collection !== null;

        $categories = Category::options();

        // Bahan beranda hanya dihitung saat tidak ada filter (halaman katalog/hasil cari tidak memakainya)
        $home = $isFiltered ? [] : $this->homeSections($products);

        return view('welcome', [
            'products'    => $products,
            'categories'  => $categories,
            'activeBrand' => $activeBrand,
            'collection'  => $collection,
            'isFiltered'  => $isFiltered,
            ...$home,
        ]);
    }

    // Koleksi bertema di beranda: [judul, deskripsi singkat]
    public const COLLECTIONS = [
        'baru'  => ['Baru masuk di Batam', 'Barang preloved yang baru ditayangkan penjual.'],
        'video' => ['Ada Video Proof', 'Kondisi asli barang bisa kamu lihat lewat video dari penjual.'],
        'kirim' => ['Bisa dikirim ke seluruh Indonesia', 'Bayar aman lewat escrow, barang dikirim ke alamatmu.'],
        'cod'   => ['Siap COD di Batam', 'Ketemuan, cek barangnya, lalu bayar di tempat.'],
    ];

    // "Baru masuk" = semua barang tayang; koleksi lain hanya barang yang masih bisa dibeli
    private static function inCollection(Product $p, string $key): bool
    {
        if ($key === 'baru') {
            return true;
        }

        return $p->status === 'Available' && match ($key) {
            'video' => $p->hasVideoProof(),
            'kirim' => !empty($p->shippingOptions()),
            'cod'   => $p->supportsCod(),
            default => false,
        };
    }

    /**
     * Data beranda tanpa filter. Semua dari data asli: koleksi disaring dari produk yang sudah
     * dimuat (tanpa query tambahan), angka statistik yang nol tidak ditampilkan.
     */
    private function homeSections($products): array
    {
        // "Belanja berdasarkan kategori": jumlah barang tayang per kategori dalam satu query
        $categoryCounts = Product::visibleInCatalog()
            ->selectRaw('kategori, COUNT(*) as total')
            ->groupBy('kategori')
            ->pluck('total', 'kategori');

        $sections = collect(self::COLLECTIONS)
            ->map(function (array $meta, string $key) use ($products) {
                $items = $products->filter(fn (Product $p) => self::inCollection($p, $key));

                return ['key' => $key, 'title' => $meta[0], 'subtitle' => $meta[1], 'total' => $items->count(), 'products' => $items->take(10)->values()];
            })
            ->filter(fn (array $s) => $s['total'] > 0)
            ->values();

        $stats = array_filter([
            'barang tayang'         => $products->count(),
            'penjual terverifikasi' => User::where('role', 'penjual')->where('seller_status', 'verified')->whereNull('deactivated_at')->count(),
            'transaksi selesai'     => Booking::where('status_cod', 'Completed')->count() + Order::where('status', Order::COMPLETED)->count(),
        ]);

        return [
            'categoryCounts' => $categoryCounts,
            'sections'       => $sections,
            'stats'          => $stats,
            'brandShowcase'  => $this->brandShowcase(),
        ];
    }

    /**
     * "Belanja berdasarkan merek": merek aktif yang punya barang tayang lebih dulu (terbanyak di
     * depan). Kalau kurang dari 8, dilengkapi merek populer yang belum punya barang.
     */
    private function brandShowcase()
    {
        $brands = Brand::active()->withCatalogCount()->get();

        $withStock = $brands->filter(fn (Brand $b) => $b->catalog_count > 0)
            ->sortBy([['catalog_count', 'desc'], ['is_popular', 'desc'], ['name', 'asc']])
            ->take(12);

        $fill = $brands->filter(fn (Brand $b) => (int) $b->catalog_count === 0 && $b->is_popular)
            ->sortBy([['sort_order', 'asc'], ['name', 'asc']])
            ->take(max(0, 8 - $withStock->count()));

        return $withStock->concat($fill)->values();
    }

    public function store(Request $request)
    {
        // Form di dashboard dikunci untuk penjual yang belum lolos KYC; cek juga di server
        $user = Auth::user();
        if ($user->role !== 'penjual' || $user->seller_status !== 'verified') {
            return redirect()->route('dashboard')->with('error', 'Hanya penjual terverifikasi yang bisa menayangkan barang.');
        }

        $formKey = FormToken::validate($request, 'product');

        // Klik ganda saat upload video yang lama: formulir yang sama tidak membuat produk kedua
        if ($this->productAlreadyCreated($formKey)) {
            return redirect()->route('dashboard')->with('success', 'Barang ini sudah ditayangkan.');
        }

        if (!$user->canListMoreProducts()) {
            return redirect()->route('subscription.show')->with('limit_reached', true);
        }

        $this->validateProduct($request, isNew: true);

        $paths = [];
        try {
            // Kunci baris penjual: dua upload bersamaan tidak bisa sama-sama lolos cek kuota gratis
            $created = DB::transaction(function () use ($request, $user, $formKey, &$paths) {
                $seller = User::whereKey($user->id)->lockForUpdate()->first();

                if (!$seller->canListMoreProducts()) {
                    return false;
                }

                $imagePaths = $this->storeImages($request);
                $videoPath = $request->file('video_proof')->store('product-videos', 'public');
                $paths = [...$imagePaths, $videoPath];

                $product = Product::create([
                    'user_id'          => $seller->id,
                    'image_url'        => $imagePaths[0],
                    'image_path'       => $imagePaths[0],
                    'video_proof_path' => $videoPath,
                    'status'           => 'Available',
                    'idempotency_key'  => $formKey,
                    ...$this->productAttributes($request),
                ]);

                $this->syncImages($product, $imagePaths);

                return true;
            });
        } catch (UniqueConstraintViolationException) {
            // Formulir yang sama selesai lebih dulu di request lain
            Storage::disk('public')->delete($paths);

            return redirect()->route('dashboard')->with('success', 'Barang ini sudah ditayangkan.');
        }

        if (!$created) {
            Storage::disk('public')->delete($paths);

            return redirect()->route('subscription.show')->with('limit_reached', true);
        }

        return redirect()->back()->with('success', 'Barang preloved berhasil ditayangkan!');
    }

    /** @return list<string> path foto di disk public, sesuai urutan unggah */
    private function storeImages(Request $request): array
    {
        return collect($request->file('images', []))
            ->map(fn ($file) => $file->store('products', 'public'))
            ->values()
            ->all();
    }

    /** @param list<string> $paths urutan 0 = foto utama */
    private function syncImages(Product $product, array $paths): void
    {
        $product->images()->delete();
        $product->images()->createMany(
            collect($paths)->map(fn ($path, $i) => ['path' => $path, 'urutan' => $i])->all()
        );
    }

    private function productAlreadyCreated(string $formKey): bool
    {
        return Product::where('idempotency_key', $formKey)->where('user_id', Auth::id())->exists();
    }

    // Halaman detail produk: COD untuk penjual Batam, pilih pengiriman untuk penjual luar Batam
    public function show(Product $product)
    {
        if ($product->status === 'Booked' && Booking::releaseExpiredPayments() > 0) {
            $product->refresh();
        }
        $product->load(['user', 'brand', 'images']);
        $isOwner = (int) Auth::id() === (int) $product->user_id;

        // Produk yang disembunyikan karena kuota penjual habis hanya bisa dilihat pemiliknya
        $isHidden = in_array($product->status, Product::ACTIVE_STATUSES, true)
            && !Product::visibleInCatalog()->whereKey($product->id)->exists();
        // Produk yang diturunkan admin juga hanya bisa dilihat pemiliknya (dan admin)
        $canSeeHidden = $isOwner || Auth::user()?->role === 'admin';
        abort_if(($isHidden || $product->isTakenDown()) && !$canSeeHidden, 404);

        return view('products.show', [
            'product'    => $product,
            'isOwner'    => $isOwner,
            'categories' => Category::options(),
        ]);
    }

    public function edit($id)
    {
        $product = Product::with('images')->where('id', $id)->where('user_id', Auth::id())->firstOrFail();

        return view('products.edit', ['product' => $product, 'sellerInBatam' => Auth::user()->isInBatam()]);
    }

    public function update(Request $request, $id)
    {
        $product = Product::where('id', $id)->where('user_id', Auth::id())->firstOrFail();

        // Video boleh dikosongkan saat edit: video lama dipertahankan, produk lama tanpa video tetap bisa diedit
        $this->validateProduct($request, isNew: false, currentBrandId: $product->brand_id);

        $attributes = $this->productAttributes($request);

        // Foto baru menggantikan seluruh galeri lama; kalau tidak ada foto yang diunggah, galeri tetap
        $newImages = [];
        $oldImages = [];
        if ($request->hasFile('images')) {
            $oldImages = $product->storedImagePaths();
            $newImages = $this->storeImages($request);
            $attributes['image_url'] = $attributes['image_path'] = $newImages[0];
        }

        $oldVideo = null;
        if ($request->hasFile('video_proof')) {
            $oldVideo = $product->video_proof_path;
            $attributes['video_proof_path'] = $request->file('video_proof')->store('product-videos', 'public');
        }

        DB::transaction(function () use ($product, $attributes, $newImages) {
            $product->update($attributes);

            if ($newImages) {
                $this->syncImages($product, $newImages);
            }
        });

        // File lama baru dihapus setelah data tersimpan, supaya produk tidak menunjuk file yang hilang
        Storage::disk('public')->delete(array_values(array_filter([...$oldImages, $oldVideo])));

        return redirect()->route('dashboard')->with('success', 'Barang "' . $product->title . '" berhasil diperbarui.');
    }

    private function validateProduct(Request $request, bool $isNew, ?int $currentBrandId = null): void
    {
        $videoMaxMb = (int) config('thriftlo.video_proof.max_mb');

        // Baris opsi pengiriman yang dibiarkan kosong di form diabaikan
        $request->merge([
            'shipping_options' => collect($request->input('shipping_options', []))
                ->filter(fn ($o) => is_array($o) && (trim((string) ($o['courier'] ?? '')) !== '' || trim((string) ($o['cost'] ?? '')) !== ''))
                ->values()
                ->all(),
        ]);

        $request->validateWithBag('product', [
            'nama_barang'     => 'required|string|max:255',
            'mode_jual'       => ['required', Rule::in(['ecer', 'borongan'])],
            'kategori'        => ['required', Rule::in(Category::slugs())],
            // Merek opsional: id merek aktif dari daftar (atau merek lama produk ini), atau "lainnya" + teks bebas
            'brand_id'        => ['nullable', Rule::when(
                $request->input('brand_id') !== self::OTHER_BRAND,
                [Rule::exists('brands', 'id')->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $currentBrandId ?? 0))]
            )],
            'brand_other'     => ['nullable', 'required_if:brand_id,' . self::OTHER_BRAND, 'string', 'max:60'],
            'harga'           => 'required|numeric|min:0',
            'grade'           => ['required', Rule::in(config('thriftlo.grades'))],
            // 1-5 foto; saat edit boleh dikosongkan (galeri lama dipertahankan)
            'images'          => [$isNew ? 'required' : 'nullable', 'array', 'min:1', 'max:' . Product::MAX_IMAGES],
            'images.*'        => ['required', 'file', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            // Video Proof wajib untuk barang baru; mov = rekaman kamera iPhone
            'video_proof'     => [$isNew ? 'required' : 'nullable', 'file', 'mimes:mp4,mov,webm', 'max:' . ($videoMaxMb * 1024)],
            'deskripsi'       => 'nullable|string|max:2000',
            // Penjual di luar Batam tidak bisa COD, jadi wajib menyediakan pengiriman
            'shipping_options'           => [Auth::user()->isInBatam() ? 'nullable' : 'required', 'array', 'max:10'],
            'shipping_options.*.courier' => 'required|string|max:60',
            'shipping_options.*.cost'    => 'required|integer|min:0|max:10000000',
        ], [
            'nama_barang.required' => 'Nama barang wajib diisi.',
            'kategori.in'          => 'Pilih kategori dari daftar yang tersedia.',
            'brand_id.exists'      => 'Pilih merek dari daftar, atau pilih "Merek lain" lalu ketik mereknya.',
            'brand_other.required_if' => 'Tulis nama mereknya, atau kosongkan pilihan merek kalau barangnya tanpa merek.',
            'brand_other.max'      => 'Nama merek maksimal 60 karakter.',
            'grade.in'             => 'Pilih grade kondisi dari daftar yang tersedia.',
            'harga.required'       => 'Harga wajib diisi.',
            'harga.numeric'        => 'Harga harus berupa angka.',
            'harga.min'            => 'Harga tidak boleh negatif.',
            'images.required'      => 'Unggah minimal 1 foto produk.',
            'images.array'         => 'Foto produk gagal diunggah. Coba pilih ulang fotonya.',
            'images.min'           => 'Unggah minimal 1 foto produk.',
            'images.max'           => 'Maksimal ' . Product::MAX_IMAGES . ' foto per produk.',
            'images.*.required'    => 'Foto produk gagal diunggah. Coba pilih ulang fotonya.',
            'images.*.file'        => 'Foto produk gagal diunggah. Coba pilih ulang fotonya.',
            'images.*.uploaded'    => 'Foto produk gagal diunggah. Pastikan tiap foto tidak lebih dari 2MB.',
            'images.*.image'       => 'Foto produk harus berupa gambar (JPG, PNG, atau WEBP).',
            'images.*.mimes'       => 'Foto produk harus berformat JPG, PNG, atau WEBP.',
            'images.*.max'         => 'Ukuran tiap foto produk maksimal 2MB.',
            'video_proof.required' => 'Video Proof wajib diunggah. Rekam video singkat yang menunjukkan kondisi asli barang.',
            'video_proof.file'     => 'Video Proof gagal diunggah. Coba pilih ulang videonya.',
            // File yang melebihi batas upload server dibuang PHP, jadi muncul sebagai gagal unggah, bukan "max"
            'video_proof.uploaded' => "Video Proof gagal diunggah. Pastikan ukurannya tidak lebih dari {$videoMaxMb}MB lalu coba lagi.",
            'video_proof.mimes'    => 'Video Proof harus berformat MP4, MOV, atau WEBM.',
            'video_proof.max'      => "Ukuran Video Proof maksimal {$videoMaxMb}MB. Rekam lebih singkat (maks. 60 detik) atau kecilkan resolusinya.",
            'shipping_options.required'           => 'Lapakmu di luar Batam, jadi tambahkan minimal 1 opsi pengiriman.',
            'shipping_options.max'                => 'Maksimal 10 opsi pengiriman.',
            'shipping_options.*.courier.required' => 'Nama kurir wajib diisi di setiap opsi pengiriman.',
            'shipping_options.*.courier.max'      => 'Nama kurir maksimal 60 karakter.',
            'shipping_options.*.cost.required'    => 'Ongkir wajib diisi di setiap opsi pengiriman.',
            'shipping_options.*.cost.integer'     => 'Ongkir harus berupa angka rupiah tanpa titik atau koma.',
            'shipping_options.*.cost.min'         => 'Ongkir tidak boleh negatif.',
            'shipping_options.*.cost.max'         => 'Ongkir maksimal Rp10.000.000.',
        ]);
    }

    private function productAttributes(Request $request): array
    {
        $shipping = collect($request->input('shipping_options', []))
            ->map(fn ($o) => ['courier' => trim($o['courier']), 'cost' => (int) $o['cost']])
            ->all();

        return [
            'title'            => $request->nama_barang,
            'mode_jual'        => $request->mode_jual,
            'kategori'         => $request->kategori,
            'price'            => $request->harga,
            'grade'            => $request->grade,
            'description'      => $request->deskripsi,
            'shipping_options' => $shipping ?: null,
            ...$this->brandAttributes($request),
        ];
    }

    // Nilai dropdown merek untuk "Merek lain" (input teks bebas)
    public const OTHER_BRAND = 'lainnya';

    /** @return array{brand_id: ?int, brand_other: ?string} */
    private function brandAttributes(Request $request): array
    {
        $choice = $request->input('brand_id');

        if ($choice === self::OTHER_BRAND) {
            $other = trim((string) $request->input('brand_other'));
            // Merek yang diketik ternyata ada di daftar: simpan sebagai merek daftar supaya ikut tampil di halaman merek
            $known = Brand::findActiveByName($other);

            return $known
                ? ['brand_id' => $known->id, 'brand_other' => null]
                : ['brand_id' => null, 'brand_other' => $other];
        }

        return ['brand_id' => filled($choice) ? (int) $choice : null, 'brand_other' => null];
    }

    /** @return array{lat: float, lng: float}|null lokasi acuan COD penjual produk ini */
    private function sellerCodPoint(Product $product): ?array
    {
        $seller = $product->user;

        return $seller?->hasCodPoint() ? ['lat' => $seller->cod_latitude, 'lng' => $seller->cod_longitude] : null;
    }

    /**
     * Saran tempat COD yang adil untuk popup booking (JSON). Lokasi pembeli opsional (?lat=&lng=);
     * tanpa lokasi pembeli, saran diurutkan dari yang terdekat dengan penjual.
     */
    public function codSuggestions(Request $request, Product $product)
    {
        abort_unless($product->supportsCod(), 404);

        $data = $request->validate([
            'lat' => 'nullable|required_with:lng|numeric|between:-90,90',
            'lng' => 'nullable|required_with:lat|numeric|between:-180,180',
        ]);
        $buyer = isset($data['lat'], $data['lng']) ? ['lat' => (float) $data['lat'], 'lng' => (float) $data['lng']] : null;

        return response()->json(CodSpots::suggest($buyer, $this->sellerCodPoint($product)));
    }

    /**
     * Titik ketemuan usulan pembeli, divalidasi dengan aturan keadilan (CodSpots::violation): di wilayah
     * Batam, dan maksimal cod_point.max_each_km dari penjual (kalau punya lokasi acuan) dan dari pembeli
     * (kalau lokasinya dikirim). Jarak dihitung ulang di server (angka dari browser tidak dipercaya)
     * lalu disimpan ke booking.
     *
     * @return array<string, float|null>
     */
    private function meetingPointAttributes(Request $request, Product $product): array
    {
        $meeting = ['lat' => (float) $request->input('meeting_latitude'), 'lng' => (float) $request->input('meeting_longitude')];
        $buyer = $request->filled('buyer_latitude') && $request->filled('buyer_longitude')
            ? ['lat' => (float) $request->input('buyer_latitude'), 'lng' => (float) $request->input('buyer_longitude')]
            : null;
        $seller = $this->sellerCodPoint($product);

        if ($violation = CodSpots::violation($meeting, $buyer, $seller)) {
            throw ValidationException::withMessages([$violation['field'] => $violation['message']])->errorBag('booking');
        }

        return [
            'meeting_latitude'   => round($meeting['lat'], 7),
            'meeting_longitude'  => round($meeting['lng'], 7),
            'buyer_latitude'     => $buyer ? round($buyer['lat'], 7) : null,
            'buyer_longitude'    => $buyer ? round($buyer['lng'], 7) : null,
            'seller_distance_km' => $seller ? CodSpots::km($meeting, $seller) : null,
            'buyer_distance_km'  => $buyer ? CodSpots::km($meeting, $buyer) : null,
        ];
    }

    public function bookProduct(Request $request, $id)
    {
        if (!Auth::check()) {
            return redirect()->route('login')->with('error', 'Silakan login terlebih dahulu!');
        }

        // Admin hanya mengelola platform, tidak ikut berbelanja
        if (Auth::user()->isAdmin()) {
            return redirect()->route('product.show', $id)->with('error', User::ADMIN_CANNOT_BUY_MESSAGE);
        }

        $noteMax = (int) config('thriftlo.cod_point.meeting_note_max');
        // Spasi di awal/akhir tidak dihitung, sama seperti penghitung karakter di popup
        $request->merge(['lokasi_cod' => trim((string) $request->input('lokasi_cod'))]);
        $request->validateWithBag('booking', [
            // Keterangan tempat ketemuan; titik pastinya ada di meeting_latitude/longitude
            'lokasi_cod'        => 'required|string|max:' . $noteMax,
            'waktu_cod'         => 'required',
            'meeting_latitude'  => 'required|numeric|between:-90,90',
            'meeting_longitude' => 'required|numeric|between:-180,180',
            // Lokasi pembeli opsional (hanya dari tombol "Gunakan lokasi saya"), tapi harus berpasangan
            'buyer_latitude'    => 'nullable|required_with:buyer_longitude|numeric|between:-90,90',
            'buyer_longitude'   => 'nullable|required_with:buyer_latitude|numeric|between:-180,180',
        ], [
            'lokasi_cod.required'        => 'Tulis keterangan tempat ketemuan, mis. "Depan Mega Mall".',
            'lokasi_cod.max'             => "Keterangan tempat maksimal {$noteMax} karakter.",
            'waktu_cod.required'         => 'Pilih jadwal ketemuan.',
            'meeting_latitude.required'  => 'Pasang pin titik ketemuan di peta dulu.',
            'meeting_longitude.required' => 'Pasang pin titik ketemuan di peta dulu.',
            'meeting_latitude.numeric'   => 'Koordinat titik ketemuan tidak valid.',
            'meeting_longitude.numeric'  => 'Koordinat titik ketemuan tidak valid.',
            'meeting_latitude.between'   => 'Latitude titik ketemuan harus di antara -90 dan 90.',
            'meeting_longitude.between'  => 'Longitude titik ketemuan harus di antara -180 dan 180.',
            'buyer_latitude.*'           => 'Lokasimu tidak terbaca dengan benar. Tekan "Gunakan lokasi saya" lagi.',
            'buyer_longitude.*'          => 'Lokasimu tidak terbaca dengan benar. Tekan "Gunakan lokasi saya" lagi.',
        ]);
        $formKey = FormToken::validate($request);

        // Barang yang tertahan tagihan QRIS kedaluwarsa dilepas dulu supaya bisa di-booking lagi
        Booking::releaseExpiredPayments();
        $product = Product::findOrFail($id);

        // Kiriman ulang formulir yang sama (klik ganda, refresh, retry): tampilkan booking yang sudah ada
        if ($existing = Booking::where('idempotency_key', $formKey)->first()) {
            return $this->bookingAlreadyCreated($existing, $product);
        }

        if (strtolower($product->status) !== 'available') {
            return redirect()->back()->with('error', 'Yah, barang ini keburu di-booking orang lain. Cek barang lain yang mirip, yuk!');
        }

        // COD hanya untuk penjual di Batam; penjual luar Batam lewat pengiriman
        if (!$product->supportsCod()) {
            return redirect()->route('product.show', $product)->with('error', 'COD hanya tersedia untuk penjual di Batam. Pilih opsi pengiriman di bawah, ya.');
        }

        // Barang yang disembunyikan karena kuota penjual habis tidak bisa di-booking lewat URL langsung
        if (!Product::visibleInCatalog()->whereKey($product->id)->exists()) {
            return redirect()->back()->with('error', 'Barang ini sedang tidak ditayangkan penjualnya.');
        }

        $meetingPoint = $this->meetingPointAttributes($request, $product);

        $gateway = app(BookingFeeGateway::class);
        $viaGateway = !$gateway->isSimulation();

        // Mode simulasi: tombol "Saya sudah bayar" di popup, tanpa payment gateway. Sebelum membayar
        // belum ada booking yang dibuat, jadi batal di tahap ringkasan/QRIS tidak dikenai apa pun.
        // Mode Midtrans: booking dibuat menunggu pembayaran, lunas setelah webhook settlement.
        if (!$viaGateway && !$request->boolean('qris_paid')) {
            return redirect()->back()->with('error', 'Selesaikan pembayaran biaya booking lewat QRIS dulu untuk mengunci barangnya.');
        }

        $fee = (int) config('thriftlo.booking.fee');

        try {
            // Kunci baris produk: booking dan checkout Kirim untuk barang yang sama saling menunggu,
            // lalu status dicek ulang di dalam kunci supaya barang tidak terjual dua kali.
            $booking = DB::transaction(function () use ($request, $product, $fee, $formKey, $viaGateway, $meetingPoint) {
                $locked = Product::visibleInCatalog()->whereKey($product->id)->lockForUpdate()->first();

                if (!$locked || $locked->status !== 'Available') {
                    return null;
                }

                $booking = Booking::create([
                    'product_id'      => $locked->id,
                    'user_id'         => Auth::id(),
                    'qr_token'        => 'TL-' . strtoupper(Str::random(8)),
                    'idempotency_key' => $formKey,
                    'cod_location'    => $request->lokasi_cod,
                    'cod_schedule'    => $request->waktu_cod,
                    ...$meetingPoint,
                    'status_cod'       => $viaGateway ? Booking::AWAITING_PAYMENT : 'Pending',
                    'booking_fee'      => $fee,
                    'fee_status'       => $viaGateway ? Booking::FEE_PENDING : Booking::FEE_PAID,
                    'paid_at'          => $viaGateway ? null : now(),
                    'payment_order_id' => $viaGateway ? 'TL-BKG-' . Str::ulid() : null,
                ]);

                $locked->update(['status' => 'Booked']);

                return $booking;
            });
        } catch (UniqueConstraintViolationException) {
            // Pengaman terakhir di database: token formulir sudah dipakai, atau barang sudah punya booking aktif
            if ($existing = Booking::where('idempotency_key', $formKey)->first()) {
                return $this->bookingAlreadyCreated($existing, $product);
            }
            $booking = null;
        }

        if (!$booking) {
            return redirect()->back()->with('error', 'Barang sudah tidak tersedia. Barusan ada yang lebih dulu mem-booking atau membelinya. Cek barang lain yang mirip, yuk!');
        }

        if ($viaGateway) {
            return $this->startQrisPayment($gateway, $booking);
        }

        return redirect()->route('bookings.index')->with('success', 'Pembayaran biaya booking Rp' . number_format($fee, 0, ',', '.') . ' berhasil. Barangnya udah dikunci buat kamu. Tinggal ketemuan sama penjualnya dan tunjukin token ' . $booking->qr_token . ' di bawah ini.');
    }

    // Tagihan QRIS dibuat di luar transaksi supaya panggilan ke gateway tidak menahan kunci baris
    private function startQrisPayment(BookingFeeGateway $gateway, Booking $booking)
    {
        try {
            $charge = $gateway->createQrisCharge($booking);
        } catch (PaymentException $e) {
            Log::error('Tagihan QRIS biaya booking gagal dibuat', [
                'booking_id' => $booking->id,
                'order_id'   => $booking->payment_order_id,
                'error'      => $e->getMessage(),
            ]);
            $booking->failPayment('error');

            return redirect()->route('product.show', $booking->product_id)
                ->with('error', 'Pembayaran QRIS sedang tidak bisa dibuat. Barangnya belum dikunci dan kamu tidak dikenai biaya. Coba lagi sebentar lagi, ya.');
        }

        $booking->forceFill([
            'payment_qr_string'  => $charge->qrString,
            'payment_qr_url'     => $charge->qrImageUrl,
            'payment_expires_at' => $charge->expiresAt,
            'payment_status'     => $charge->status,
        ])->save();

        return redirect()->route('booking.pay', $booking);
    }

    // Formulir booking yang sama dikirim lagi: jangan buat booking/bayar kedua, tunjukkan yang sudah ada
    private function bookingAlreadyCreated(Booking $existing, Product $product)
    {
        if ((int) $existing->user_id !== (int) Auth::id() || (int) $existing->product_id !== (int) $product->id) {
            return redirect()->route('product.show', $product)->with('error', FormToken::REUSED_MESSAGE);
        }

        // Tagihan QRIS-nya masih berjalan atau sudah gagal: jangan tagih ulang
        if ($existing->isAwaitingPayment()) {
            return redirect()->route('booking.pay', $existing);
        }
        if ($existing->payment_order_id && $existing->fee_status === null) {
            return redirect()->route('product.show', $product)->with('error', 'Pembayaran booking sebelumnya tidak selesai, jadi barangnya tidak dikunci. Silakan booking ulang.');
        }

        return redirect()->route('bookings.index')->with('success', 'Booking ini sudah dibuat dan biaya bookingnya sudah tercatat, jadi tidak ditagih lagi. Tunjukkan token ' . $existing->qr_token . ' saat ketemuan.');
    }

    public function sellerDashboard()
    {
        $user = Auth::user();
        Booking::releaseExpiredPayments();
        $myProducts = Product::where('user_id', $user->id)->latest()->get();
        $myBookings = Booking::with(['product', 'user'])
            ->whereHas('product', function($q) use ($user) {
                $q->where('user_id', $user->id);
            })->latest()->get();

        // Produk aktif di atas kuota gratis yang sedang disembunyikan dari katalog
        $hiddenProductIds = $user->hasActiveSubscription()
            ? []
            : $myProducts->whereIn('status', Product::ACTIVE_STATUSES)->whereNull('taken_down_at')->sortBy('id')
                ->slice((int) config('thriftlo.subscription.free_product_limit'))->pluck('id')->all();

        // Pesanan lewat pengiriman untuk barang milik penjual
        Order::releaseOverdue();
        $myOrders = Order::with(['product', 'user'])
            ->whereHas('product', fn ($q) => $q->where('user_id', $user->id))
            ->latest()->get();

        return view('dashboard', compact('myProducts', 'myBookings', 'hiddenProductIds', 'myOrders'));
    }

    public function verifyQrCode(Request $request)
    {
        $request->validate([
            'qr_code_token' => 'required|string',
        ]);

        $cleanToken = strtoupper(trim($request->qr_code_token));

        // Kunci baris booking lalu produk (urutan sama dengan pembatalan) supaya verifikasi dan
        // pembatalan yang datang bersamaan tidak menghasilkan status yang saling bertentangan.
        $result = DB::transaction(function () use ($cleanToken) {
            // Hanya booking untuk barang milik penjual yang sedang login
            $booking = Booking::where('qr_token', $cleanToken)
                ->whereHas('product', fn ($q) => $q->where('user_id', Auth::id()))
                ->lockForUpdate()
                ->first();

            if (!$booking) {
                return 'not_found';
            }
            if ($booking->status_cod === 'Cancelled' || $booking->status_cod === 'Completed') {
                return $booking->status_cod;
            }
            // Biaya booking belum lunas (mode Midtrans): token belum berlaku
            if (!$booking->isActive()) {
                return 'unpaid';
            }

            $booking->update(['status_cod' => 'Completed']);
            Product::whereKey($booking->product_id)->lockForUpdate()->first()?->update(['status' => 'Sold Out']);

            return 'ok';
        });

        return match ($result) {
            'ok'        => redirect()->back()->with('success', 'Transaksi COD Sukses! Status barang berubah menjadi Sold Out.'),
            'Cancelled' => redirect()->back()->with('error', 'Token QR ini sudah tidak berlaku karena booking-nya dibatalkan.'),
            'Completed' => redirect()->back()->with('error', 'Token QR ini sudah pernah diverifikasi sebelumnya!'),
            'unpaid'    => redirect()->back()->with('error', 'Biaya booking untuk token ini belum lunas, jadi token belum berlaku.'),
            default     => redirect()->back()->with('error', 'Token QR tidak ditemukan atau tidak valid!'),
        };
    }

    public function destroy($id)
    {
        $product = Product::where('id', $id)->where('user_id', Auth::id())->firstOrFail();

        // Booking ikut terhapus (cascade), jadi barang yang masih di-booking atau ditunggu pembayaran
        // QRIS-nya tidak boleh dihapus: settlement yang datang sesudahnya tidak akan punya booking lagi.
        // Baris produk dikunci seperti di bookProduct supaya booking baru tidak masuk di sela pengecekan.
        // Dikumpulkan sebelum dihapus: baris product_images ikut terhapus (cascade)
        $imagePaths = $product->storedImagePaths();

        $deleted = DB::transaction(function () use ($product) {
            Product::whereKey($product->id)->lockForUpdate()->first();

            if (Booking::where('active_product_id', $product->id)->exists()) {
                return false;
            }

            $product->delete();

            return true;
        });

        if (!$deleted) {
            return redirect()->back()->with('error', 'Barang ini sedang di-booking pembeli, jadi belum bisa dihapus. Batalkan booking-nya dulu dari dashboard, atau tunggu pembayaran QRIS-nya kedaluwarsa.');
        }

        Storage::disk('public')->delete($imagePaths);

        if ($product->video_proof_path) {
            Storage::disk('public')->delete($product->video_proof_path);
        }

        return redirect()->back()->with('success', 'Barang preloved berhasil dihapus dari inventaris!');
    }
}

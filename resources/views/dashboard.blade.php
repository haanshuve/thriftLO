<x-market-layout title="Toko Saya">

    @php
        $user = Auth::user();
        $isSeller = $user->role === 'penjual';
        $isVerified = $isSeller && $user->seller_status === 'verified';
        $categories = config('thriftlo.categories');
        $myProducts = $myProducts ?? collect();
        $myBookings = $myBookings ?? collect();

        $countAvailable = $myProducts->filter(fn ($p) => strtolower($p->status) === 'available')->count();
        $countBooked = $myProducts->filter(fn ($p) => strtolower($p->status) === 'booked')->count();
        $countSold = $myProducts->filter(fn ($p) => strtolower($p->status) === 'sold out')->count();
        $countPendingCod = $myBookings->whereIn('status_cod', ['Pending', 'Confirmed'])->count();

        $statusCodClass = [
            'Pending'   => 'bg-amber-100 text-amber-800',
            'Confirmed' => 'bg-sky-100 text-sky-800',
            'Completed' => 'bg-emerald-100 text-emerald-800',
            'Cancelled' => 'bg-slate-200 text-slate-600',
        ];
        $statusCodLabel = [
            'Pending'   => 'Menunggu COD',
            'Confirmed' => 'Dikonfirmasi',
            'Completed' => 'Selesai',
            'Cancelled' => 'Dibatalkan',
        ];
        $imgSrc = fn ($p) => filter_var($p->image_url ?? $p->image_path, FILTER_VALIDATE_URL)
            ? ($p->image_url ?? $p->image_path)
            : asset('storage/' . ($p->image_url ?? $p->image_path));
        $openUploadOnLoad = $errors->product->any();

        // Kuota produk gratis & langganan
        $hiddenProductIds = $hiddenProductIds ?? [];
        $freeLimit = (int) config('thriftlo.subscription.free_product_limit');
        $isSubscribed = $user->hasActiveSubscription();
        $activeCount = $countAvailable + $countBooked;
        $atLimit = !$isSubscribed && $activeCount >= $freeLimit;
    @endphp

    <main class="max-w-7xl mx-auto px-4 sm:px-6 py-4 sm:py-6 space-y-5">

        @unless($isSeller)
            <section class="rounded-xl border border-slate-200 p-6 text-center">
                <p class="text-4xl mb-2" aria-hidden="true">🏬</p>
                <h1 class="text-lg font-bold text-slate-900">Halaman ini khusus penjual</h1>
                <p class="text-sm text-slate-500 mt-1">Akunmu terdaftar sebagai pembeli. Lihat transaksi booking-mu di halaman Transaksi.</p>
                <a href="{{ route('bookings.index') }}" class="inline-block mt-4 px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700">Ke Transaksi Saya</a>
            </section>
        @else

        <!-- Kepala toko -->
        <section class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-14 h-14 shrink-0 rounded-xl bg-emerald-600 text-white text-2xl font-black flex items-center justify-center" aria-hidden="true">
                    {{ strtoupper(substr($user->nama_toko ?: $user->name, 0, 1)) }}
                </div>
                <div class="min-w-0">
                    <h1 class="text-lg sm:text-xl font-bold text-slate-900 truncate">{{ $user->nama_toko ?: $user->name }}</h1>
                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-slate-500">
                        <span>📍 {{ $user->locationLabel() }}</span>
                        @if($isVerified)
                            <span class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-full">✓ Penjual terverifikasi</span>
                        @elseif($user->seller_status === 'rejected')
                            <span class="inline-flex items-center gap-1 text-xs font-semibold text-rose-700 bg-rose-50 border border-rose-200 px-2 py-0.5 rounded-full">Verifikasi ditolak</span>
                        @else
                            <span class="inline-flex items-center gap-1 text-xs font-semibold text-amber-800 bg-amber-50 border border-amber-200 px-2 py-0.5 rounded-full">⏳ Menunggu verifikasi</span>
                        @endif
                    </div>
                </div>
            </div>

            @if($isVerified && $atLimit)
                {{-- Kuota gratis penuh: arahkan ke halaman langganan, bukan form upload --}}
                <a href="{{ route('subscription.show') }}" class="shrink-0 inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold">
                    <span aria-hidden="true" class="text-lg leading-none">+</span> Jual Barang
                </a>
            @elseif($isVerified)
                <button type="button" onclick="openUploadModal()" class="shrink-0 inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold">
                    <span aria-hidden="true" class="text-lg leading-none">+</span> Jual Barang
                </button>
            @else
                <button type="button" disabled class="shrink-0 inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-lg bg-slate-100 text-slate-400 text-sm font-semibold cursor-not-allowed" aria-describedby="kycStatus">
                    🔒 Jual Barang
                </button>
            @endif
        </section>

        <!-- Notifikasi -->
        @if(session('success'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">✅ {{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" role="alert">⚠️ {{ session('error') }}</div>
        @endif

        <!-- Status KYC -->
        @if($user->seller_status === 'pending')
            <div id="kycStatus" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                <p class="font-semibold">Dokumen KYC sedang ditinjau admin</p>
                <p class="text-amber-800 mt-0.5">Foto KTP & selfie kamu diperiksa maksimal 1×24 jam. Setelah disetujui, kamu bisa mulai menayangkan barang.</p>
            </div>
        @elseif($user->seller_status === 'rejected')
            <div id="kycStatus" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                <p class="font-semibold">Verifikasi penjual ditolak</p>
                <p class="mt-0.5">Dokumen KYC kamu belum bisa disetujui. Hubungi admin thriftLO lewat chat untuk informasi lebih lanjut.</p>
            </div>
        @endif

        <!-- Paket & kuota produk -->
        @if($isVerified)
            <a href="{{ route('subscription.show') }}" class="flex items-center gap-3 rounded-xl border px-4 py-3 transition hover:shadow-sm {{ $isSubscribed ? 'border-emerald-200 bg-emerald-50/60' : ($atLimit ? 'border-amber-200 bg-amber-50' : 'border-slate-200') }}">
                <span class="text-2xl" aria-hidden="true">{{ $isSubscribed ? '⭐' : '📦' }}</span>
                <div class="flex-1 min-w-0 text-sm">
                    @if($isSubscribed)
                        <p class="font-semibold text-slate-900">Langganan Unlimited · sisa {{ $user->subscriptionDaysLeft() }} hari</p>
                        <p class="text-slate-600">Aktif sampai {{ $user->subscription_expires_at->locale('id')->translatedFormat('j F Y') }}. Tayangkan produk tanpa batas.</p>
                    @elseif(count($hiddenProductIds) > 0)
                        <p class="font-semibold text-amber-900">{{ count($hiddenProductIds) }} produk disembunyikan dari katalog</p>
                        <p class="text-amber-800">Langgananmu sudah habis. Berlangganan lagi supaya semua produk tampil.</p>
                    @else
                        <p class="font-semibold {{ $atLimit ? 'text-amber-900' : 'text-slate-900' }}">Paket Gratis · {{ $activeCount }}/{{ $freeLimit }} produk aktif</p>
                        <p class="{{ $atLimit ? 'text-amber-800' : 'text-slate-500' }}">
                            {{ $atLimit ? 'Kuota penuh. Berlangganan Rp5.000/bulan untuk produk tanpa batas.' : 'Butuh lebih dari ' . $freeLimit . ' produk? Lihat paket Unlimited.' }}
                        </p>
                    @endif
                </div>
                <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
            </a>
        @endif

        <!-- Statistik -->
        <section class="grid grid-cols-2 lg:grid-cols-4 gap-3" aria-label="Ringkasan toko">
            @foreach([
                ['Barang tayang', $countAvailable, 'text-emerald-700'],
                ['Sedang di-booking', $countBooked, 'text-amber-700'],
                ['Terjual', $countSold, 'text-slate-900'],
                ['Janji COD aktif', $countPendingCod, 'text-sky-700'],
            ] as [$label, $value, $color])
                <div class="rounded-xl border border-slate-200 px-4 py-3">
                    <p class="text-xs text-slate-500">{{ $label }}</p>
                    <p class="text-2xl font-bold tabular-nums {{ $color }}">{{ $value }}</p>
                </div>
            @endforeach
        </section>

        <div class="grid lg:grid-cols-3 gap-5 items-start">

            <!-- Verifikasi QR COD -->
            <section class="lg:order-2 rounded-xl border border-emerald-200 bg-emerald-50/60 p-4 sm:p-5">
                <h2 class="font-bold text-slate-900">Verifikasi token QR</h2>
                <p class="text-sm text-slate-600 mt-1">Saat bertemu pembeli, minta ia menunjukkan token QR di HP-nya, lalu masukkan di sini untuk menyelesaikan transaksi.</p>
                <form action="{{ route('booking.verify') }}" method="POST" class="mt-3 flex gap-2">
                    @csrf
                    <label for="qr_code_token" class="sr-only">Token QR pembeli</label>
                    <input type="text" id="qr_code_token" name="qr_code_token" placeholder="TL-XXXXXXXX" required autocomplete="off" autocapitalize="characters"
                           class="flex-1 min-w-0 border-slate-300 rounded-lg text-sm uppercase font-mono font-semibold focus:border-emerald-500 focus:ring-emerald-500">
                    <button type="submit" class="shrink-0 px-4 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold">Verifikasi</button>
                </form>
            </section>

            <!-- Janji COD -->
            <section class="lg:order-1 lg:col-span-2 rounded-xl border border-slate-200">
                <div class="px-4 py-3 border-b border-slate-200 flex items-baseline justify-between">
                    <h2 class="font-bold text-slate-900">Janji COD</h2>
                    <span class="text-xs text-slate-400">{{ $myBookings->count() }} booking</span>
                </div>
                <ul class="divide-y divide-slate-100">
                    @forelse($myBookings as $booking)
                        @php $bp = $booking->product; @endphp
                        <li class="p-3 sm:p-4 flex gap-3">
                            <img src="{{ $bp ? $imgSrc($bp) : '' }}" alt="" class="w-14 h-14 rounded-lg object-cover bg-slate-100 shrink-0"
                                 onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1523381210434-271e8be1f52b?auto=format&fit=crop&w=200&q=70'">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-start justify-between gap-2">
                                    <p class="text-sm font-semibold text-slate-900 line-clamp-1">{{ $bp->title ?? 'Produk dihapus' }}</p>
                                    <span class="shrink-0 text-[11px] font-semibold px-2 py-0.5 rounded-full {{ $statusCodClass[$booking->status_cod] ?? 'bg-slate-100 text-slate-600' }}">
                                        {{ $statusCodLabel[$booking->status_cod] ?? $booking->status_cod }}
                                    </span>
                                </div>
                                <p class="text-xs text-slate-500 mt-0.5">Pembeli: <span class="font-medium text-slate-700">{{ $booking->user->name ?? '-' }}</span></p>
                                <p class="text-xs text-slate-500 mt-0.5">
                                    📍 {{ $booking->cod_location }} · ⏰ {{ \Illuminate\Support\Carbon::parse($booking->cod_schedule)->format('d M Y, H:i') }}
                                </p>
                                {{-- Token sengaja disamarkan: penjual harus meminta token dari HP pembeli saat COD --}}
                                <p class="text-[11px] font-mono text-slate-400 mt-1" title="Minta pembeli menunjukkan token QR saat bertemu">TOKEN: TL-••••••••</p>
                            </div>
                        </li>
                    @empty
                        <li class="px-4 py-8 text-center text-sm text-slate-500">Belum ada pembeli yang booking barangmu.</li>
                    @endforelse
                </ul>
            </section>
        </div>

        <!-- Pesanan lewat pengiriman -->
        @php $myOrders = $myOrders ?? collect(); @endphp
        @if($myOrders->isNotEmpty() || !$user->isInBatam())
            <section class="rounded-xl border border-slate-200">
                <div class="px-4 py-3 border-b border-slate-200 flex items-baseline justify-between">
                    <h2 class="font-bold text-slate-900">Pesanan pengiriman</h2>
                    <span class="text-xs text-slate-400">{{ $myOrders->where('status', \App\Models\Order::AWAITING_SHIPMENT)->count() }} perlu dikirim</span>
                </div>
                <ul class="divide-y divide-slate-100">
                    @forelse($myOrders as $order)
                        @php $op = $order->product; $awaiting = $order->status === \App\Models\Order::AWAITING_SHIPMENT; @endphp
                        <li class="p-3 sm:p-4 flex flex-col sm:flex-row gap-3">
                            <div class="flex gap-3 flex-1 min-w-0">
                                <img src="{{ $imgSrc($op) }}" alt="" class="w-14 h-14 rounded-lg object-cover bg-slate-100 shrink-0"
                                     onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1523381210434-271e8be1f52b?auto=format&fit=crop&w=200&q=70'">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-start justify-between gap-2">
                                        <p class="text-sm font-semibold text-slate-900 line-clamp-1">{{ $op->title }}</p>
                                        <span class="shrink-0 text-[11px] font-semibold px-2 py-0.5 rounded-full {{ $awaiting ? 'bg-amber-100 text-amber-800' : 'bg-sky-100 text-sky-800' }}">{{ $order->statusLabel() }}</span>
                                    </div>
                                    <p class="text-xs text-slate-500 mt-0.5">Pembeli: <span class="font-medium text-slate-700">{{ $order->user->name ?? '-' }}</span> · {{ $order->created_at->format('d M Y, H:i') }}</p>
                                    <p class="text-xs text-slate-500 mt-0.5">🚚 {{ $order->courier }} · Ongkir Rp{{ number_format($order->shipping_cost, 0, ',', '.') }} · <span class="font-semibold text-slate-700">Total Rp{{ number_format($order->total_price, 0, ',', '.') }}</span></p>
                                    <p class="text-xs text-slate-500 mt-0.5 whitespace-pre-line">🏠 {{ $order->shipping_address }}</p>
                                    @if($order->shipped_at)
                                        <p class="text-xs text-slate-400 mt-0.5">Dikirim {{ $order->shipped_at->format('d M Y, H:i') }}</p>
                                    @endif
                                </div>
                            </div>
                            @if($awaiting)
                                <form action="{{ route('order.ship', $order) }}" method="POST" class="sm:self-center"
                                      onsubmit="return confirm('Tandai pesanan &quot;{{ addslashes($op->title) }}&quot; sudah dikirim?')">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="w-full sm:w-auto h-9 px-4 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold">Tandai Sudah Dikirim</button>
                                </form>
                            @endif
                        </li>
                    @empty
                        <li class="px-4 py-8 text-center text-sm text-slate-500">Belum ada pesanan. Pembeli dari luar Batam bisa memesan lewat opsi pengiriman di produkmu.</li>
                    @endforelse
                </ul>
            </section>
        @endif

        <!-- Inventaris -->
        <section>
            <div class="flex items-baseline justify-between mb-3">
                <h2 class="text-base sm:text-lg font-bold text-slate-900">Barang saya</h2>
                <span class="text-xs text-slate-400">{{ $myProducts->count() }} barang</span>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5 gap-3 sm:gap-4">
                @forelse($myProducts as $item)
                    @php
                        $status = strtolower($item->status);
                        [$statusText, $statusClass] = match ($status) {
                            'available' => ['Tayang', 'bg-emerald-600 text-white'],
                            'booked'    => ['Di-booking', 'bg-amber-400 text-amber-950'],
                            default     => ['Terjual', 'bg-slate-700 text-white'],
                        };
                        $kondisi = preg_match('/\(([^)]+)\)/', (string) $item->grade, $m) ? $m[1] : ($item->grade ?: 'Preloved');
                    @endphp
                    <article class="bg-white rounded-xl border border-slate-200 overflow-hidden flex flex-col">
                        <div class="relative aspect-square bg-slate-100 overflow-hidden">
                            <img src="{{ $imgSrc($item) }}" alt="{{ $item->title }}" loading="lazy"
                                 class="w-full h-full object-cover {{ $status === 'available' ? '' : 'opacity-60' }}"
                                 onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1523381210434-271e8be1f52b?auto=format&fit=crop&w=500&q=80'">
                            <span class="absolute top-2 left-2 text-[11px] font-bold px-2 py-0.5 rounded-md shadow-sm {{ $statusClass }}">{{ $statusText }}</span>
                            @if(in_array($item->id, $hiddenProductIds, true))
                                <span class="absolute inset-x-0 bottom-0 bg-slate-900/75 text-white text-xs font-semibold text-center py-1.5">🙈 Disembunyikan</span>
                            @endif
                            @if($item->mode_jual === 'borongan')
                                <span class="absolute top-2 right-2 text-[11px] font-bold px-2 py-0.5 rounded-md bg-white/90 text-slate-700 shadow-sm">📦 Borongan</span>
                            @endif
                        </div>
                        <div class="p-2.5 sm:p-3 flex flex-col flex-1">
                            <h3 class="text-sm text-slate-800 leading-snug line-clamp-2 min-h-[2.5rem]" title="{{ $item->title }}">{{ $item->title }}</h3>
                            <p class="mt-1 text-base font-bold text-emerald-700 tabular-nums">Rp{{ number_format($item->price, 0, ',', '.') }}</p>
                            <p class="mt-0.5 text-[11px] text-slate-500 truncate">{{ $categories[$item->kategori]['label'] ?? ($item->kategori ?: 'Tanpa kategori') }} · {{ $kondisi }}</p>
                            @if($item->video_proof)
                                <a href="{{ $item->video_proof }}" target="_blank" rel="noopener" class="mt-0.5 text-[11px] font-semibold text-sky-700 hover:underline">🎥 Lihat video proof</a>
                            @endif
                            @if($item->shipping_options)
                                <p class="mt-0.5 text-[11px] text-slate-500">🚚 {{ count($item->shipping_options) }} opsi pengiriman</p>
                            @endif
                            <div class="mt-auto pt-2.5 flex gap-1.5">
                                <a href="{{ route('product.edit', $item->id) }}" class="flex-1 h-8 inline-flex items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-700 text-xs font-semibold">
                                    Edit
                                </a>
                                <form action="{{ route('product.destroy', $item->id) }}" method="POST" class="flex-1"
                                      onsubmit="return confirm('Hapus &quot;{{ addslashes($item->title) }}&quot; dari katalog?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="w-full h-8 rounded-lg border border-slate-200 text-slate-500 hover:border-rose-300 hover:bg-rose-50 hover:text-rose-700 text-xs font-semibold">
                                        Hapus
                                    </button>
                                </form>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="col-span-full text-center py-12 rounded-xl border border-dashed border-slate-300">
                        <p class="text-4xl mb-2" aria-hidden="true">📦</p>
                        <p class="font-bold text-slate-800">Belum ada barang yang ditayangkan</p>
                        @if($isVerified)
                            <button type="button" onclick="openUploadModal()" class="mt-3 px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold">+ Jual barang pertamamu</button>
                        @else
                            <p class="text-sm text-slate-500 mt-1">Kamu bisa mulai berjualan setelah verifikasi KYC disetujui.</p>
                        @endif
                    </div>
                @endforelse
            </div>
        </section>

        @endunless
    </main>

    @if($isVerified)
        <!-- Modal Jual Barang -->
        <div id="uploadModal" class="fixed inset-0 bg-slate-900/60 {{ $openUploadOnLoad ? 'flex' : 'hidden' }} items-end sm:items-center justify-center z-50 sm:p-4" role="dialog" aria-modal="true" aria-labelledby="uploadTitle">
            <div class="bg-white w-full sm:max-w-lg rounded-t-2xl sm:rounded-2xl shadow-xl max-h-[92vh] overflow-y-auto">
                <div class="sticky top-0 bg-white flex justify-between items-center px-5 sm:px-6 py-4 border-b border-slate-100">
                    <h3 id="uploadTitle" class="text-base font-bold text-slate-900">Jual barang preloved</h3>
                    <button type="button" onclick="closeUploadModal()" class="w-8 h-8 rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-600 text-xl" aria-label="Tutup">&times;</button>
                </div>

                <form action="{{ route('product.store') }}" method="POST" enctype="multipart/form-data" class="px-5 sm:px-6 py-4 space-y-3.5">
                    @csrf

                    @if($errors->product->any())
                        <div class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2.5 text-sm text-rose-700" role="alert">
                            <p class="font-semibold">Barang belum tersimpan:</p>
                            <ul class="list-disc list-inside mt-0.5">
                                @foreach($errors->product->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                            <p class="mt-1 text-rose-600">Foto produk perlu dipilih ulang.</p>
                        </div>
                    @endif

                    @include('products.partials.form-fields', ['product' => null, 'sellerInBatam' => $user->isInBatam()])

                    <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2.5 rounded-lg text-sm">
                        Tayangkan ke katalog
                    </button>
                </form>
            </div>
        </div>

        <script>
            function openUploadModal() {
                const modal = document.getElementById('uploadModal');
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                document.getElementById('nama_barang').focus();
            }

            function closeUploadModal() {
                const modal = document.getElementById('uploadModal');
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }

            document.getElementById('uploadModal').addEventListener('click', function (e) {
                if (e.target === this) closeUploadModal();
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') closeUploadModal();
            });
        </script>
    @endif
</x-market-layout>

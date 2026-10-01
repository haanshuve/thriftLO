<x-market-layout :title="$product->title">

    @php
        $seller = $product->user;
        $img = filter_var($product->image_url ?? $product->image_path, FILTER_VALIDATE_URL)
            ? ($product->image_url ?? $product->image_path)
            : asset('storage/' . ($product->image_url ?? $product->image_path));
        $status = strtolower($product->status);
        $isAvailable = $status === 'available';
        $canCod = $product->supportsCod();
        $shippingOptions = $product->shippingOptions();
        $price = (int) $product->price;
        $rupiah = fn ($n) => 'Rp' . number_format($n, 0, ',', '.');
        $selectedOption = old('shipping_option');
    @endphp

    <main class="max-w-5xl mx-auto px-4 sm:px-6 py-4 sm:py-6 space-y-4">

        <a href="{{ route('home') }}" class="inline-flex items-center gap-1 text-sm font-medium text-slate-500 hover:text-slate-700">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
            Kembali ke katalog
        </a>

        @if(session('error'))
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="alert">⚠️ {{ session('error') }}</div>
        @endif

        <div class="grid md:grid-cols-2 gap-5 md:gap-8 items-start">
            <!-- Foto -->
            <div class="relative aspect-square rounded-2xl overflow-hidden bg-slate-100 border border-slate-200">
                <img src="{{ $img }}" alt="{{ $product->title }}" class="w-full h-full object-cover {{ $isAvailable ? '' : 'grayscale-[40%]' }}"
                     onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1523381210434-271e8be1f52b?auto=format&fit=crop&w=800&q=80'">
                @unless($isAvailable)
                    <span class="absolute inset-x-0 bottom-0 bg-slate-900/75 text-white text-sm font-semibold text-center py-2">
                        {{ $status === 'booked' ? 'Sedang dalam transaksi' : 'Sudah terjual' }}
                    </span>
                @endunless
            </div>

            <!-- Info & aksi -->
            <div class="space-y-4">
                <div>
                    <p class="text-xs font-semibold text-slate-500">
                        {{ $categories[$product->kategori]['label'] ?? ($product->kategori ?: 'Tanpa kategori') }}
                        · {{ $product->grade ?: 'Preloved' }}
                        @if($product->mode_jual === 'borongan') · 📦 Borongan @endif
                    </p>
                    <h1 class="mt-1 text-xl sm:text-2xl font-bold text-slate-900 leading-snug">{{ $product->title }}</h1>
                    <p class="mt-2 text-2xl sm:text-3xl font-black text-emerald-700 tabular-nums">{{ $rupiah($price) }}</p>
                    <p class="mt-0.5 text-xs text-slate-500">Harga pas, sesuai yang tertera.</p>
                </div>

                <!-- Penjual -->
                <div class="flex items-center gap-3 rounded-xl border border-slate-200 px-3 py-2.5">
                    <span class="w-10 h-10 shrink-0 rounded-full bg-emerald-600 text-white font-bold flex items-center justify-center" aria-hidden="true">
                        {{ strtoupper(substr($seller->nama_toko ?: $seller->name, 0, 1)) }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-slate-900 truncate">
                            {{ $seller->nama_toko ?: $seller->name }}
                            @if($seller->seller_status === 'verified')<span class="text-emerald-600" title="Penjual terverifikasi">✓</span>@endif
                        </p>
                        <p class="text-xs text-slate-500 truncate">{{ $canCod ? '📍' : '🚚' }} {{ $seller->locationLabel() }} ·{{ $canCod ? 'Bisa COD di Batam' : 'Hanya pengiriman' }}</p>
                    </div>
                    @unless($isOwner)
                        <a href="{{ route('chat.index', ['user_id' => $seller->id, 'product_id' => $product->id]) }}" class="shrink-0 h-9 px-3 inline-flex items-center rounded-lg border border-emerald-600 text-emerald-700 hover:bg-emerald-50 text-sm font-semibold">Tanya</a>
                    @endunless
                </div>

                <!-- Aksi beli -->
                @if($isOwner)
                    <div class="rounded-xl bg-slate-50 border border-slate-200 p-4 text-sm text-slate-600 flex items-center justify-between gap-3">
                        <span>Ini barang kamu.</span>
                        <a href="{{ route('product.edit', $product->id) }}" class="h-9 px-4 inline-flex items-center rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-semibold">Edit barang</a>
                    </div>
                @elseif(!$isAvailable)
                    <div class="rounded-xl bg-slate-50 border border-slate-200 p-4 text-sm text-slate-600">
                        {{ $status === 'booked' ? 'Barang ini sedang dalam transaksi dengan pembeli lain.' : 'Barang ini sudah terjual.' }}
                        <a href="{{ route('home') }}" class="font-semibold text-emerald-700 hover:underline">Cari barang lain</a>
                    </div>
                @else
                    @if($canCod)
                        <div class="rounded-xl border border-emerald-200 bg-emerald-50/60 p-4">
                            <h2 class="font-semibold text-slate-900">Booking COD</h2>
                            <p class="text-sm text-slate-600 mt-0.5">Ketemuan di Batam, cek barangnya, baru bayar di tempat.</p>
                            @auth
                                <button type="button" data-url="{{ route('product.book', $product->id) }}" data-name="{{ $product->title }}" onclick="openBookingModal(this)"
                                        class="mt-3 w-full h-11 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold transition active:scale-[0.98] motion-reduce:transform-none">
                                    Booking COD
                                </button>
                            @else
                                <a href="{{ route('login') }}" class="mt-3 w-full h-11 inline-flex items-center justify-center rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold">Masuk untuk booking COD</a>
                            @endauth
                        </div>
                    @endif

                    @if(!empty($shippingOptions))
                        <!-- Pilih pengiriman -->
                        <form id="pengiriman" action="{{ route('order.store', $product) }}" method="POST" class="rounded-xl border border-slate-200 p-4 space-y-3 scroll-mt-20">
                            @csrf
                            <div>
                                <h2 class="font-semibold text-slate-900">Pilih Pengiriman</h2>
                                <p class="text-sm text-slate-500 mt-0.5">Dikirim dari {{ $seller->isInBatam() ? $seller->locationLabel() : 'luar Batam' }}.</p>
                            </div>

                            <fieldset class="space-y-2">
                                <legend class="sr-only">Opsi pengiriman</legend>
                                @foreach($shippingOptions as $i => $option)
                                    <label class="flex items-center gap-3 rounded-lg border border-slate-200 px-3 py-2.5 cursor-pointer hover:bg-slate-50 has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50/60">
                                        <input type="radio" name="shipping_option" value="{{ $i }}" data-cost="{{ (int) $option['cost'] }}" required
                                               @checked((string) $selectedOption === (string) $i)
                                               class="text-emerald-600 focus:ring-emerald-500">
                                        <span class="flex-1 text-sm font-medium text-slate-800">{{ $option['courier'] }}</span>
                                        <span class="text-sm font-semibold text-slate-700 tabular-nums">{{ $rupiah($option['cost']) }}</span>
                                    </label>
                                @endforeach
                            </fieldset>
                            @error('shipping_option')
                                <p class="text-sm text-rose-600" role="alert">{{ $message }}</p>
                            @enderror

                            <div>
                                <label for="shipping_address" class="block text-sm font-semibold text-slate-700 mb-1">Alamat pengiriman</label>
                                <textarea id="shipping_address" name="shipping_address" rows="3" maxlength="500" required
                                          placeholder="Nama penerima, jalan, nomor rumah, kelurahan, kecamatan, kota, kode pos"
                                          class="w-full border-slate-300 rounded-lg text-sm focus:border-emerald-500 focus:ring-emerald-500">{{ old('shipping_address') }}</textarea>
                                @error('shipping_address')
                                    <p class="mt-1 text-sm text-rose-600" role="alert">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Rincian harga -->
                            <dl class="rounded-lg bg-slate-50 px-3 py-2.5 text-sm space-y-1">
                                <div class="flex justify-between"><dt class="text-slate-500">Harga barang</dt><dd class="tabular-nums">{{ $rupiah($price) }}</dd></div>
                                <div class="flex justify-between"><dt class="text-slate-500">Ongkir</dt><dd id="ongkirText" class="tabular-nums">Pilih kurir dulu</dd></div>
                                <div class="flex justify-between border-t border-slate-200 pt-1 font-bold text-slate-900"><dt>Total</dt><dd id="totalText" class="tabular-nums">{{ $rupiah($price) }}</dd></div>
                            </dl>

                            @auth
                                <button type="submit" class="w-full h-11 rounded-lg bg-sky-600 hover:bg-sky-700 text-white text-sm font-semibold transition active:scale-[0.98] disabled:opacity-70 motion-reduce:transform-none">
                                    Checkout
                                </button>
                            @else
                                <a href="{{ route('login') }}" class="w-full h-11 inline-flex items-center justify-center rounded-lg bg-sky-600 hover:bg-sky-700 text-white text-sm font-semibold">Masuk untuk checkout</a>
                            @endauth
                        </form>

                        <script>
                            (function () {
                                const price = {{ $price }};
                                const rupiah = n => 'Rp' + n.toLocaleString('id-ID');
                                const form = document.getElementById('pengiriman');

                                // Total = harga barang + ongkir kurir yang dipilih
                                function updateTotal() {
                                    const picked = form.querySelector('input[name="shipping_option"]:checked');
                                    if (!picked) return;
                                    const cost = parseInt(picked.dataset.cost, 10);
                                    document.getElementById('ongkirText').textContent = rupiah(cost);
                                    document.getElementById('totalText').textContent = rupiah(price + cost);
                                }

                                form.addEventListener('change', updateTotal);
                                form.addEventListener('submit', () => form.querySelector('button[type="submit"]')?.setAttribute('disabled', ''));
                                updateTotal();
                            })();
                        </script>
                    @elseif(!$canCod)
                        <div class="rounded-xl bg-amber-50 border border-amber-200 p-4 text-sm text-amber-900">
                            Penjual belum mengatur opsi pengiriman untuk barang ini. Tanya penjual lewat chat, ya.
                        </div>
                    @endif
                @endif

                @if($product->description)
                    <div>
                        <h2 class="font-semibold text-slate-900">Deskripsi</h2>
                        <p class="mt-1 text-sm text-slate-600 whitespace-pre-line">{{ $product->description }}</p>
                    </div>
                @endif
                @if($product->video_proof)
                    <a href="{{ $product->video_proof }}" target="_blank" rel="noopener" class="inline-block text-sm font-semibold text-sky-700 hover:underline">🎥 Lihat video kondisi barang</a>
                @endif
            </div>
        </div>
    </main>

    @if($canCod && $isAvailable && !$isOwner)
        <x-booking-modal />
    @endif
</x-market-layout>

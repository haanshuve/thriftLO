<x-market-layout :show-categories="true">

    @php
        $activeKategori = request('kategori', 'all');
        $search = trim((string) request('search'));
        $isFiltered = $search !== '' || $activeKategori !== 'all';
        $viewer = auth()->user();
        $categoryLabel = $categories[$activeKategori]['label'] ?? $activeKategori;
    @endphp

    {{-- Tanpa JavaScript, foto tetap tampil (tanpa efek fade) --}}
    <noscript><style>.img-fade { opacity: 1 !important; }</style></noscript>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 py-4 sm:py-6 space-y-4 sm:space-y-6">

        <!-- Hero -->
        @unless($isFiltered)
            <section class="rounded-2xl bg-gradient-to-br from-emerald-600 via-emerald-600 to-teal-600 text-white px-4 py-4 sm:px-8 sm:py-7 flex items-center justify-between gap-3 sm:gap-6 shadow-lg shadow-emerald-900/10">
                <div class="min-w-0">
                    <h1 class="text-base sm:text-3xl font-black leading-tight tracking-tight">Selamatkan barang bagus dari tumpukan lemari.</h1>
                    <p class="hidden sm:block text-sm sm:text-base text-emerald-50 mt-2 max-w-xl">Preloved pilihan dari penjual terverifikasi di Batam. Harga jelas, tanya kondisi lewat chat, ketemuan, bayar di tempat.</p>
                </div>
                <div class="flex gap-3 sm:gap-6 shrink-0 text-right sm:text-left" title="Perkiraan dari jumlah barang yang ditayangkan dan dibooking">
                    <div>
                        <p class="text-base sm:text-3xl font-black tabular-nums">{{ number_format($wastePreventedKg ?? 0, 1, ',', '.') }}<span class="text-[10px] sm:text-sm font-bold"> kg</span></p>
                        <p class="text-[10px] sm:text-xs text-emerald-100">barang terselamatkan</p>
                    </div>
                    <div>
                        <p class="text-base sm:text-3xl font-black tabular-nums">{{ $totalBookings ?? 0 }}</p>
                        <p class="text-[10px] sm:text-xs text-emerald-100">transaksi COD</p>
                    </div>
                </div>
            </section>
        @endunless

        <!-- Notifikasi -->
        @if(session('success'))
            <div class="animate-fade-up flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 shadow-sm" role="status">
                <span class="w-7 h-7 shrink-0 rounded-full bg-emerald-600 text-white flex items-center justify-center" aria-hidden="true">✓</span>
                <p class="pt-1">{{ session('success') }}</p>
            </div>
        @endif
        @if(session('error'))
            <div class="animate-fade-up flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 shadow-sm" role="alert">
                <span class="w-7 h-7 shrink-0 rounded-full bg-amber-400 text-amber-950 flex items-center justify-center font-bold" aria-hidden="true">!</span>
                <p class="pt-1">{{ session('error') }}</p>
            </div>
        @endif

        <!-- Katalog -->
        <section aria-labelledby="judulKatalog">
            <div class="flex items-baseline justify-between mb-3">
                <h2 id="judulKatalog" class="text-base sm:text-lg font-bold text-slate-900">
                    @if($search !== '')
                        Hasil buat “{{ $search }}”
                    @elseif($activeKategori !== 'all')
                        {{ $categoryLabel }}
                    @else
                        Baru masuk di Batam
                    @endif
                </h2>
                @if($products->isNotEmpty())
                    <span class="text-xs text-slate-400">{{ $products->count() }} barang</span>
                @endif
            </div>

            @if($products->isEmpty())
                @if($isFiltered)
                    <x-empty-state illustration="search" :title="$search !== '' ? 'Belum nemu “' . $search . '”' : 'Belum ada barang di ' . $categoryLabel">
                        Coba kata kunci lain, atau posting di Request Barang biar penjual yang nyariin buat kamu.
                        <x-slot:actions>
                            <a href="{{ route('requests.index') }}" class="inline-flex items-center h-10 px-4 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold transition active:scale-95 motion-reduce:transform-none">Buat Request Barang</a>
                            <a href="{{ route('home') }}" class="inline-flex items-center h-10 px-4 rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-sm font-semibold transition active:scale-95 motion-reduce:transform-none">Lihat semua barang</a>
                        </x-slot:actions>
                    </x-empty-state>
                @else
                    <x-empty-state title="Katalog lagi kosong nih">
                        Barang preloved keren bakal segera nongol di sini. Punya barang nganggur di lemari? Jadi yang pertama jualan!
                        <x-slot:actions>
                            @if($viewer?->role === 'penjual')
                                <a href="{{ route('dashboard') }}" class="inline-flex items-center h-10 px-4 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold transition active:scale-95 motion-reduce:transform-none">Upload barang pertamamu</a>
                            @elseif($viewer)
                                <a href="{{ route('requests.index') }}" class="inline-flex items-center h-10 px-4 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold transition active:scale-95 motion-reduce:transform-none">Cari lewat Request Barang</a>
                            @else
                                <a href="{{ route('register') }}" class="inline-flex items-center h-10 px-4 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold transition active:scale-95 motion-reduce:transform-none">Mulai jualan</a>
                            @endif
                        </x-slot:actions>
                    </x-empty-state>
                @endif
            @else
                <div id="katalog" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5 gap-3 sm:gap-4">
                    @foreach($products as $p)
                        @php
                            $name = $p->title;
                            $img = filter_var($p->image_url ?? $p->image_path, FILTER_VALIDATE_URL)
                                ? ($p->image_url ?? $p->image_path)
                                : asset('storage/' . ($p->image_url ?? $p->image_path));
                            // "Grade A (Like New)" -> "Like New"
                            $kondisi = preg_match('/\(([^)]+)\)/', (string) $p->grade, $m) ? $m[1] : ($p->grade ?: 'Preloved');
                            $gradeLetter = preg_match('/Grade\s+([ABC])/i', (string) $p->grade, $g) ? strtoupper($g[1]) : null;
                            $kondisiClass = match ($gradeLetter) {
                                'A' => 'bg-emerald-600 text-white',
                                'B' => 'bg-amber-400 text-amber-950',
                                'C' => 'bg-slate-700 text-white',
                                default => 'bg-white/90 text-slate-700',
                            };
                            $isAvailable = strtolower($p->status) === 'available';
                            $isOwn = (int) auth()->id() === (int) $p->user_id;
                            $canCod = $p->supportsCod();
                            $detailUrl = route('product.show', $p);
                        @endphp

                        <article class="group bg-white rounded-xl border border-slate-200/80 overflow-hidden flex flex-col shadow-sm shadow-slate-200/70 transition duration-300 ease-out hover:-translate-y-1 hover:shadow-xl hover:shadow-emerald-900/10 hover:border-emerald-200 focus-within:ring-2 focus-within:ring-emerald-500/40 motion-reduce:transition-none motion-reduce:hover:translate-y-0">
                            <a href="{{ $detailUrl }}" class="relative block aspect-square bg-slate-100 overflow-hidden animate-pulse" aria-label="Lihat detail {{ $name }}">
                                <img src="{{ $img }}" alt="{{ $name }}" loading="lazy"
                                     class="img-fade w-full h-full object-cover opacity-0 transition duration-500 group-hover:scale-105 motion-reduce:transition-none motion-reduce:group-hover:scale-100 {{ $isAvailable ? '' : 'grayscale-[40%]' }}"
                                     onload="this.classList.remove('opacity-0'); this.parentElement.classList.remove('animate-pulse')"
                                     onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1523381210434-271e8be1f52b?auto=format&fit=crop&w=500&q=80'">

                                <span class="absolute top-2 left-2 text-[10px] sm:text-[11px] font-bold px-2 py-0.5 rounded-md shadow-sm {{ $kondisiClass }}">{{ $kondisi }}</span>

                                @if($p->mode_jual === 'borongan')
                                    <span class="absolute top-2 right-2 text-[10px] sm:text-[11px] font-bold px-2 py-0.5 rounded-md bg-white/90 text-slate-700 shadow-sm">📦 Borongan</span>
                                @endif

                                @unless($isAvailable)
                                    <span class="absolute inset-x-0 bottom-0 bg-slate-900/75 text-white text-xs font-semibold text-center py-1.5">Udah di-booking orang</span>
                                @endunless
                            </a>

                            <div class="p-2.5 sm:p-3 flex flex-col flex-1">
                                <h3 class="text-sm text-slate-800 leading-snug line-clamp-2 min-h-[2.5rem]" title="{{ $name }}">
                                    <a href="{{ $detailUrl }}" class="hover:text-emerald-700">{{ $name }}</a>
                                </h3>

                                <p class="mt-1 text-base sm:text-lg font-bold text-emerald-700 tabular-nums">Rp{{ number_format($p->price, 0, ',', '.') }}</p>

                                <p class="mt-1 text-[11px] text-slate-500 truncate">{{ $canCod ? '📍' : '🚚' }} {{ $p->user->locationLabel() }} ·{{ $p->user->nama_toko ?? $p->user->name ?? 'Penjual' }}</p>

                                <div class="mt-auto pt-2.5 flex gap-1.5">
                                    @if($isOwn)
                                        <span class="w-full text-center text-xs font-semibold text-slate-500 bg-slate-100 rounded-lg py-2">Ini barang kamu</span>
                                    @else
                                        <a href="{{ route('chat.index', ['user_id' => $p->user_id, 'product_id' => $p->id]) }}"
                                           class="shrink-0 w-9 h-9 flex items-center justify-center rounded-lg border border-emerald-600 text-emerald-700 hover:bg-emerald-50 transition active:scale-90 motion-reduce:transform-none"
                                           aria-label="Tanya penjual soal {{ $name }}" title="Tanya penjual">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12Z"/></svg>
                                        </a>
                                        @if($isAvailable && $canCod)
                                            <button type="button" data-url="{{ route('product.book', $p->id) }}" data-name="{{ $name }}" onclick="openBookingModal(this)"
                                                    class="flex-1 h-9 rounded-lg bg-emerald-600 hover:bg-emerald-700 hover:shadow-md hover:shadow-emerald-600/30 text-white text-xs sm:text-sm font-semibold transition active:scale-95 motion-reduce:transform-none">
                                                Booking COD
                                            </button>
                                        @elseif($isAvailable)
                                            {{-- Penjual di luar Batam: tidak ada COD, pilih kurir di halaman detail --}}
                                            <a href="{{ $detailUrl }}#pengiriman"
                                               class="flex-1 h-9 inline-flex items-center justify-center rounded-lg bg-sky-600 hover:bg-sky-700 hover:shadow-md hover:shadow-sky-600/30 text-white text-xs sm:text-sm font-semibold transition active:scale-95 motion-reduce:transform-none">
                                                <span class="sm:hidden">Pengiriman</span><span class="hidden sm:inline">Pilih Pengiriman</span>
                                            </a>
                                        @else
                                            <button type="button" disabled class="flex-1 h-9 rounded-lg bg-slate-100 text-slate-400 text-xs sm:text-sm font-semibold cursor-not-allowed">
                                                Di-booking
                                            </button>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        <!-- Kenapa thriftLO (trust signal) -->
        <section aria-labelledby="judulTrust" class="pt-2">
            <h2 id="judulTrust" class="text-base sm:text-lg font-bold text-slate-900 mb-3">Thrifting tenang di thriftLO</h2>
            <div class="grid sm:grid-cols-3 gap-3">
                @foreach([
                    ['🔐', 'COD aman pakai token QR', 'Bayar pas barang udah di tangan. Transaksi baru selesai setelah penjual memasukkan token dari HP kamu.'],
                    ['🪪', 'Penjual dicek KTP-nya', 'Setiap penjual diverifikasi KTP dan selfie-nya oleh admin thriftLO sebelum boleh jualan.'],
                    ['🌱', 'Hemat dan ramah bumi', 'Satu barang yang dipakai lagi berarti satu barang lebih sedikit di TPA. Gaya dapet, dompet aman.'],
                ] as [$icon, $title, $text])
                    <div class="rounded-xl border border-slate-200/80 bg-white p-4 shadow-sm shadow-slate-200/70 transition duration-300 hover:shadow-md motion-reduce:transition-none">
                        <span class="w-10 h-10 rounded-full bg-emerald-50 flex items-center justify-center text-xl" aria-hidden="true">{{ $icon }}</span>
                        <h3 class="mt-3 font-semibold text-slate-900">{{ $title }}</h3>
                        <p class="mt-1 text-sm text-slate-600">{{ $text }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        <footer class="pt-2 pb-4 text-center text-xs text-slate-400">
            thriftLO · Marketplace preloved khusus Batam · © {{ now()->year }}
        </footer>
    </main>

    <x-booking-modal />
</x-market-layout>

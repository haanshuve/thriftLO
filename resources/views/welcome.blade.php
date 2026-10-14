<x-market-layout :show-categories="true">

    @php
        $activeKategori = request('kategori', 'all');
        $search = trim((string) request('search'));
        $brandFilter = trim((string) request('merek'));
        $isFiltered = $search !== '' || $activeKategori !== 'all' || $brandFilter !== '';
        $viewer = auth()->user();
        // Admin hanya mengelola platform: tombol booking & pengiriman diganti "Lihat detail"
        $isAdminViewer = (bool) $viewer?->isAdmin();
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

        {{-- Belanja berdasarkan merek: hanya merek yang punya barang tayang --}}
        @if(!$isFiltered && $brandShowcase->isNotEmpty())
            <section aria-labelledby="judulMerek">
                <div class="flex items-baseline justify-between mb-3">
                    <h2 id="judulMerek" class="text-base sm:text-lg font-bold text-slate-900">Belanja berdasarkan merek</h2>
                    <a href="{{ route('brands.index') }}" class="text-sm font-semibold text-emerald-700 hover:underline">Lihat semua merek</a>
                </div>
                <div class="flex gap-3 overflow-x-auto no-scrollbar snap-x -mx-4 px-4 sm:mx-0 sm:px-0 pb-1">
                    @foreach($brandShowcase as $brand)
                        <a href="{{ route('brands.show', $brand) }}"
                           class="group snap-start shrink-0 w-28 sm:w-32 rounded-xl border border-slate-200/80 bg-white p-2 shadow-sm shadow-slate-200/70 transition hover:-translate-y-0.5 hover:shadow-md hover:border-emerald-200 motion-reduce:transition-none motion-reduce:hover:translate-y-0">
                            <x-brand-wordmark :brand="$brand" />
                            <span class="block mt-1.5 text-sm font-semibold text-slate-900 truncate group-hover:text-emerald-700">{{ $brand->name }}</span>
                            <span class="block text-xs text-slate-500">{{ $brand->catalog_count }} barang</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        <!-- Katalog -->
        <section aria-labelledby="judulKatalog">
            <div class="flex items-baseline justify-between mb-3">
                <h2 id="judulKatalog" class="text-base sm:text-lg font-bold text-slate-900">
                    @if($search !== '')
                        Hasil buat “{{ $search }}”@if($activeBrand) di {{ $activeBrand->name }}@endif
                    @elseif($activeBrand)
                        Merek {{ $activeBrand->name }}
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
                    <x-empty-state illustration="search" :title="$search !== '' ? 'Belum nemu “' . $search . '”' : 'Belum ada barang ' . ($activeKategori !== 'all' ? 'di ' . $categoryLabel : 'merek ' . ($activeBrand?->name ?? $brandFilter))">
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
                        @include('products.partials.card', ['p' => $p, 'isAdminViewer' => $isAdminViewer])
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

    @unless($isAdminViewer)
        <x-booking-modal />
    @endunless
</x-market-layout>

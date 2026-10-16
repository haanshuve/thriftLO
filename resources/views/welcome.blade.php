<x-market-layout :show-categories="$isFiltered">

    @php
        $activeKategori = request('kategori', 'all');
        $search = trim((string) request('search'));
        $brandFilter = trim((string) request('merek'));
        $viewer = auth()->user();
        // Admin hanya mengelola platform: tombol booking & pengiriman diganti "Lihat detail"
        $isAdminViewer = (bool) $viewer?->isAdmin();
        $categoryLabel = $categories[$activeKategori]['label'] ?? $activeKategori;
        $collectionTitle = $collection ? \App\Http\Controllers\ProductController::COLLECTIONS[$collection][0] : null;
        // Ajakan jadi penjual: tamu -> daftar (peran penjual terpilih), penjual -> Toko Saya
        $sellerCta = match (true) {
            !$viewer                   => [route('register', ['role' => 'penjual']), 'Daftar jadi penjual'],
            $viewer->role === 'penjual' => [route('dashboard'), 'Buka Toko Saya'],
            default                    => [null, 'Pelajari caranya'],
        };
    @endphp

    {{-- Tanpa JavaScript, foto tetap tampil (tanpa efek fade) --}}
    <noscript><style>.img-fade { opacity: 1 !important; }</style></noscript>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 py-4 sm:py-6 space-y-6 sm:space-y-8">

        <!-- Notifikasi -->
        @if(session('success') || session('error'))
            <div class="space-y-3">
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
            </div>
        @endif

        @unless($isFiltered)
            {{-- ===================== Beranda ===================== --}}
            @include('home.hero')
            @include('home.highlights')
            @include('home.shop-by')

            @if($sections->isEmpty())
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
            @else
                @include('home.product-rows')
            @endif

            @include('home.info')
            @include('home.footer')
        @else
            {{-- ===================== Katalog / hasil pencarian ===================== --}}
            <section aria-labelledby="judulKatalog">
                <div class="flex items-baseline justify-between mb-3">
                    <h2 id="judulKatalog" class="text-base sm:text-lg font-bold text-slate-900">
                        @if($search !== '')
                            Hasil buat “{{ $search }}”@if($activeBrand) di {{ $activeBrand->name }}@endif
                        @elseif($activeBrand)
                            Merek {{ $activeBrand->name }}
                        @elseif($activeKategori !== 'all')
                            {{ $categoryLabel }}
                        @elseif($collectionTitle)
                            {{ $collectionTitle }}
                        @endif
                    </h2>
                    @if($products->isNotEmpty())
                        <span class="text-xs text-slate-400">{{ $products->count() }} barang</span>
                    @endif
                </div>

                @if($products->isEmpty())
                    <x-empty-state illustration="search" :title="$search !== '' ? 'Belum nemu “' . $search . '”' : ($collectionTitle && $activeKategori === 'all' && !$activeBrand && $brandFilter === '' ? 'Belum ada barang di koleksi ini' : 'Belum ada barang ' . ($activeKategori !== 'all' ? 'di ' . $categoryLabel : 'merek ' . ($activeBrand?->name ?? $brandFilter)))">
                        Coba kata kunci lain, atau posting di Request Barang biar penjual yang nyariin buat kamu.
                        <x-slot:actions>
                            <a href="{{ route('requests.index') }}" class="inline-flex items-center h-10 px-4 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold transition active:scale-95 motion-reduce:transform-none">Buat Request Barang</a>
                            <a href="{{ route('home') }}" class="inline-flex items-center h-10 px-4 rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-sm font-semibold transition active:scale-95 motion-reduce:transform-none">Kembali ke beranda</a>
                        </x-slot:actions>
                    </x-empty-state>
                @else
                    <div id="katalog" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5 gap-3 sm:gap-4">
                        @foreach($products as $p)
                            @include('products.partials.card', ['p' => $p, 'isAdminViewer' => $isAdminViewer])
                        @endforeach
                    </div>
                @endif
            </section>
        @endunless
    </main>

    @unless($isAdminViewer)
        <x-booking-modal />
    @endunless
</x-market-layout>

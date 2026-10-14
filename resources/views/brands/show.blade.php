<x-market-layout :title="'Merek ' . $brand->name">

    @php
        $isAdminViewer = (bool) auth()->user()?->isAdmin();
        $chip = 'shrink-0 inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full border text-sm font-semibold transition';
        $chipOn = 'bg-emerald-600 border-emerald-600 text-white';
        $chipOff = 'bg-white border-slate-200 text-slate-600 hover:border-emerald-500 hover:text-emerald-700';
    @endphp

    <main class="max-w-7xl mx-auto px-4 sm:px-6 py-4 sm:py-6 space-y-4 sm:space-y-5">
        <a href="{{ route('brands.index') }}" class="inline-flex items-center gap-1 text-sm font-medium text-slate-500 hover:text-slate-700">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
            Semua merek
        </a>

        <!-- Kepala merek -->
        <section class="flex items-center gap-4">
            <x-brand-wordmark :brand="$brand" size="lg" />
            <div class="min-w-0">
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900">{{ $brand->name }}</h1>
                <p class="text-sm text-slate-500">{{ $total }} barang preloved tersedia</p>
                <p class="mt-1 text-xs text-slate-400">Merek diisi oleh penjual. ThriftLO tidak menjamin keaslian barang.</p>
            </div>
        </section>

        @if($total > 0)
            <!-- Filter kategori & urutan -->
            <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                <nav class="flex-1 flex gap-2 overflow-x-auto no-scrollbar -mx-4 px-4 sm:mx-0 sm:px-0" aria-label="Filter kategori">
                    <a href="{{ route('brands.show', ['brand' => $brand, 'urut' => $sort === 'terbaru' ? null : $sort]) }}"
                       class="{{ $chip }} {{ $kategori ? $chipOff : $chipOn }}" @unless($kategori) aria-current="page" @endunless>Semua</a>
                    @foreach($categoryCounts as $value => $count)
                        @php $cat = $categories[$value] ?? ['label' => $value ?: 'Lainnya', 'icon' => '📦']; @endphp
                        <a href="{{ route('brands.show', array_filter(['brand' => $brand->slug, 'kategori' => $value, 'urut' => $sort === 'terbaru' ? null : $sort])) }}"
                           class="{{ $chip }} {{ $kategori === $value ? $chipOn : $chipOff }}" @if($kategori === $value) aria-current="page" @endif>
                            <span aria-hidden="true">{{ $cat['icon'] }}</span> {{ $cat['label'] }} <span class="text-xs opacity-75">{{ $count }}</span>
                        </a>
                    @endforeach
                </nav>
                <form method="GET" action="{{ route('brands.show', $brand) }}" class="flex items-center gap-2 shrink-0">
                    @if($kategori)
                        <input type="hidden" name="kategori" value="{{ $kategori }}">
                    @endif
                    <label for="urut" class="text-sm text-slate-500">Urutkan</label>
                    <select id="urut" name="urut" onchange="this.form.submit()" class="rounded-lg border-slate-300 text-sm py-1.5 focus:border-emerald-500 focus:ring-emerald-500">
                        @foreach($sorts as $value => $label)
                            <option value="{{ $value }}" @selected($sort === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <noscript><button type="submit" class="h-9 px-3 rounded-lg border border-slate-300 text-sm font-semibold">Terapkan</button></noscript>
                </form>
            </div>
        @endif

        @if($products->isEmpty())
            <x-empty-state illustration="search" :title="$total > 0 ? 'Belum ada barang di kategori ini' : 'Belum ada barang ' . $brand->name">
                Barang {{ $brand->name }} bakal muncul di sini begitu ada penjual yang menayangkannya. Mau titip cari lewat Request Barang?
                <x-slot:actions>
                    <a href="{{ route('requests.index') }}" class="inline-flex items-center h-10 px-4 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold">Buat Request Barang</a>
                    <a href="{{ route('home') }}" class="inline-flex items-center h-10 px-4 rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-sm font-semibold">Lihat semua barang</a>
                </x-slot:actions>
            </x-empty-state>
        @else
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5 gap-3 sm:gap-4">
                @foreach($products as $p)
                    @include('products.partials.card', ['p' => $p, 'isAdminViewer' => $isAdminViewer])
                @endforeach
            </div>
        @endif
    </main>

    @unless($isAdminViewer)
        <x-booking-modal />
    @endunless
</x-market-layout>

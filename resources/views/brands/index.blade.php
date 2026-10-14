<x-market-layout title="Semua merek">

    @php
        // Merek yang punya barang tayang ditaruh di atas
        [$withStock, $empty] = $brands->partition(fn ($b) => $b->catalog_count > 0);
        $withStock = $withStock->sortByDesc('catalog_count')->values();
    @endphp

    <main class="max-w-7xl mx-auto px-4 sm:px-6 py-4 sm:py-6 space-y-6">
        <div>
            <a href="{{ route('home') }}" class="inline-flex items-center gap-1 text-sm font-medium text-slate-500 hover:text-slate-700">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                Kembali ke beranda
            </a>
            <h1 class="mt-2 text-xl sm:text-2xl font-bold text-slate-900">Semua merek</h1>
            <p class="text-sm text-slate-500 mt-0.5">Cari barang preloved dari merek favoritmu. Merek diisi oleh penjual; cek Video Proof dan tanya penjual sebelum membeli.</p>
        </div>

        @if($brands->isEmpty())
            <x-empty-state title="Belum ada merek">Daftar merek akan muncul di sini.</x-empty-state>
        @endif

        @foreach([['Ada barangnya', $withStock], ['Belum ada barang', $empty]] as [$heading, $group])
            @if($group->isNotEmpty())
                <section aria-label="{{ $heading }}">
                    <h2 class="text-sm font-bold text-slate-700 mb-3">{{ $heading }} <span class="font-normal text-slate-400">· {{ $group->count() }} merek</span></h2>
                    <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 xl:grid-cols-8 gap-3">
                        @foreach($group as $brand)
                            <a href="{{ route('brands.show', $brand) }}"
                               class="group rounded-xl border border-slate-200/80 bg-white p-2 shadow-sm shadow-slate-200/70 transition hover:-translate-y-0.5 hover:shadow-md hover:border-emerald-200 motion-reduce:transition-none motion-reduce:hover:translate-y-0 {{ $brand->catalog_count ? '' : 'opacity-70' }}">
                                <x-brand-wordmark :brand="$brand" />
                                <span class="block mt-1.5 text-sm font-semibold text-slate-900 truncate group-hover:text-emerald-700">{{ $brand->name }}</span>
                                <span class="block text-xs text-slate-500">{{ $brand->catalog_count }} barang</span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif
        @endforeach
    </main>
</x-market-layout>

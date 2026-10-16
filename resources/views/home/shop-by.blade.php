{{-- Grid kategori lalu etalase merek. Butuh: $categories, $categoryCounts, $brandShowcase --}}

{{-- Belanja berdasarkan kategori. id="kategori" = tujuan tab "Kategori" di navigasi bawah --}}
@if(!empty($categories))
    <section id="kategori" aria-labelledby="judulKategori" class="scroll-mt-20">
        <h2 id="judulKategori" class="text-base sm:text-lg font-bold text-slate-900 mb-3">Belanja berdasarkan kategori</h2>
        <div class="grid grid-cols-3 sm:grid-cols-6 gap-2 sm:gap-3">
            @foreach($categories as $value => $cat)
                @php $count = (int) ($categoryCounts[$value] ?? 0); @endphp
                <a href="{{ route('home', ['kategori' => $value]) }}"
                   class="group flex flex-col items-center text-center rounded-xl border border-slate-200/80 bg-white px-2 py-3 shadow-sm shadow-slate-200/70 transition hover:-translate-y-0.5 hover:shadow-md hover:border-emerald-200 motion-reduce:transition-none motion-reduce:hover:translate-y-0">
                    <span class="w-11 h-11 sm:w-12 sm:h-12 rounded-full bg-emerald-50 flex items-center justify-center text-2xl transition group-hover:bg-emerald-100" aria-hidden="true">{{ $cat['icon'] }}</span>
                    <span class="mt-1.5 text-sm font-semibold text-slate-900 group-hover:text-emerald-700 truncate max-w-full">{{ $cat['label'] }}</span>
                    <span class="text-[11px] text-slate-500">{{ $count }} barang</span>
                </a>
            @endforeach
        </div>
    </section>
@endif

{{-- Belanja berdasarkan merek: tepat setelah grid kategori. Merek berproduk lebih dulu; bila kurang
     dari 8 dilengkapi merek populer tanpa barang (pudar, tidak bisa diklik). Tanpa merek = tersembunyi. --}}
@if($brandShowcase->isNotEmpty())
    <section aria-labelledby="judulMerek">
        <div class="flex items-baseline justify-between mb-3">
            <h2 id="judulMerek" class="text-base sm:text-lg font-bold text-slate-900">Belanja berdasarkan merek</h2>
            <a href="{{ route('brands.index') }}" class="text-sm font-semibold text-emerald-700 hover:underline">Lihat semua merek</a>
        </div>
        <div class="flex gap-3 overflow-x-auto no-scrollbar snap-x -mx-4 px-4 sm:mx-0 sm:px-0 pb-1">
            @foreach($brandShowcase as $brand)
                @if((int) $brand->catalog_count > 0)
                    <a href="{{ route('brands.show', $brand) }}"
                       class="group snap-start shrink-0 w-28 sm:w-32 rounded-xl border border-slate-200/80 bg-white p-2 shadow-sm shadow-slate-200/70 transition hover:-translate-y-0.5 hover:shadow-md hover:border-emerald-200 motion-reduce:transition-none motion-reduce:hover:translate-y-0">
                        <x-brand-wordmark :brand="$brand" />
                        <span class="block mt-1.5 text-sm font-semibold text-slate-900 truncate group-hover:text-emerald-700">{{ $brand->name }}</span>
                        <span class="block text-xs text-slate-500">{{ $brand->catalog_count }} barang</span>
                    </a>
                @else
                    <div class="snap-start shrink-0 w-28 sm:w-32 rounded-xl border border-dashed border-slate-200 bg-white/60 p-2 opacity-60 cursor-default" aria-disabled="true" data-brand-empty>
                        <x-brand-wordmark :brand="$brand" class="grayscale" />
                        <span class="block mt-1.5 text-sm font-semibold text-slate-700 truncate">{{ $brand->name }}</span>
                        <span class="block text-xs text-slate-400">Belum ada barang</span>
                    </div>
                @endif
            @endforeach
        </div>
    </section>
@endif

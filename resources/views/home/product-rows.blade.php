{{-- Section produk bertema (hanya yang ada isinya), maks. 10 barang per baris. Butuh: $sections, $isAdminViewer --}}
@foreach($sections as $section)
    <section aria-labelledby="koleksi-{{ $section['key'] }}" data-collection="{{ $section['key'] }}">
        <div class="flex items-end justify-between gap-3 mb-3">
            <div class="min-w-0">
                <h2 id="koleksi-{{ $section['key'] }}" class="text-base sm:text-lg font-bold text-slate-900">{{ $section['title'] }}</h2>
                <p class="text-xs sm:text-sm text-slate-500 truncate">{{ $section['subtitle'] }}</p>
            </div>
            <a href="{{ route('home', ['koleksi' => $section['key']]) }}" class="shrink-0 text-sm font-semibold text-emerald-700 hover:underline">
                Lihat semua<span class="sr-only"> {{ $section['title'] }}</span> ({{ $section['total'] }})
            </a>
        </div>
        <div class="flex gap-3 sm:gap-4 overflow-x-auto no-scrollbar snap-x -mx-4 px-4 sm:mx-0 sm:px-0 pb-2">
            @foreach($section['products'] as $p)
                <div class="snap-start shrink-0 w-[46%] sm:w-[31%] md:w-[23%] xl:w-[18.4%] flex">
                    @include('products.partials.card', ['p' => $p, 'isAdminViewer' => $isAdminViewer])
                </div>
            @endforeach
        </div>
    </section>
@endforeach

{{-- Kartu produk katalog. Butuh: $p (Product dengan relasi user & brand), $isAdminViewer --}}
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
        @if($brandName = $p->brandName())
            @if($brandUrl = $p->brandUrl())
                <a href="{{ $brandUrl }}" class="mb-0.5 text-[11px] font-bold uppercase tracking-wide text-slate-500 hover:text-emerald-700 truncate">{{ $brandName }}</a>
            @else
                <p class="mb-0.5 text-[11px] font-bold uppercase tracking-wide text-slate-500 truncate">{{ $brandName }}</p>
            @endif
        @endif
        <h3class="text-sm text-slate-800 leading-snug line-clamp-2 min-h-[2.5rem]" title="{{ $name }}">
            <a href="{{ $detailUrl }}" class="hover:text-emerald-700">{{ $name }}</a>
        </h3>

        <p class="mt-1 text-base sm:text-lg font-bold text-emerald-700 tabular-nums">Rp{{ number_format($p->price, 0, ',', '.') }}</p>

        <p class="mt-1 text-[11px] text-slate-500 truncate">{{ $canCod ? '📍' : '🚚' }} {{ $p->user->locationLabel() }} ·{{ $p->user->nama_toko ?? $p->user->name ?? 'Penjual' }}</p>

        @php $canShipToo = $canCod && $isAvailable && !empty($p->shippingOptions()); @endphp
        @if($canShipToo || $p->hasVideoProof())
            <div class="mt-1.5 flex flex-wrap gap-1">
                @if($canShipToo)
                    {{-- Penjual Batam yang juga melayani pengiriman: pembeli luar Batam tetap bisa beli --}}
                    <a href="{{ $detailUrl }}#pengiriman" class="inline-flex items-center gap-1 rounded-full border border-sky-200 bg-sky-50 px-2 py-0.5 text-[10px] sm:text-[11px] font-semibold text-sky-700 hover:bg-sky-100" title="Bisa dikirim ke luar Batam">
                        🚚 Bisa Kirim
                    </a>
                @endif
                @if($p->hasVideoProof())
                    <a href="{{ $detailUrl }}#videoProofTitle" class="inline-flex items-center gap-1 rounded-full border border-violet-200 bg-violet-50 px-2 py-0.5 text-[10px] sm:text-[11px] font-semibold text-violet-700 hover:bg-violet-100" title="Penjual mengunggah video kondisi asli barang">
                        🎥 Ada Video Proof
                    </a>
                @endif
            </div>
        @endif

        <div class="mt-auto pt-2.5 flex gap-1.5">
            @if($isOwn)
                <span class="w-full text-center text-xs font-semibold text-slate-500 bg-slate-100 rounded-lg py-2">Ini barang kamu</span>
            @else
                <a href="{{ route('chat.index', ['user_id' => $p->user_id, 'product_id' => $p->id]) }}"
                   class="shrink-0 w-9 h-9 flex items-center justify-center rounded-lg border border-emerald-600 text-emerald-700 hover:bg-emerald-50 transition active:scale-90 motion-reduce:transform-none"
                   aria-label="Tanya penjual soal {{ $name }}" title="Tanya penjual">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12Z"/></svg>
                </a>
                @if($isAvailable && $isAdminViewer)
                    <a href="{{ $detailUrl }}"
                       class="flex-1 h-9 inline-flex items-center justify-center rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-50 text-xs sm:text-sm font-semibold">
                        Lihat detail
                    </a>
                @elseif($isAvailable && $canCod)
                    <button type="button" data-url="{{ route('product.book', $p->id) }}" data-name="{{ $name }}" data-price="{{ (int) $p->price }}" onclick="openBookingModal(this)"
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

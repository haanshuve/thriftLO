{{-- Kartu mini barang terbaru di slide hero. Butuh: $p (Product), $compact (bool, versi ponsel) --}}
<a href="{{ route('product.show', $p) }}"
   class="group block rounded-xl bg-white text-slate-900 overflow-hidden shadow-md shadow-emerald-950/20 transition hover:-translate-y-0.5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white motion-reduce:transition-none motion-reduce:hover:translate-y-0"
   data-hero-product="{{ $p->id }}">
    <div class="{{ $compact ? 'h-16' : 'aspect-square' }} bg-emerald-50 overflow-hidden">
        <img src="{{ \App\Models\Product::publicImageUrl($p->image_url ?? $p->image_path) }}" alt="{{ $p->title }}"
             loading="lazy" decoding="async" class="w-full h-full object-cover"
             onerror="this.onerror=null; this.style.visibility='hidden'">
    </div>
    <div class="{{ $compact ? 'px-1.5 py-1' : 'px-2.5 py-2' }}">
        <p class="{{ $compact ? 'text-[11px]' : 'text-xs sm:text-sm' }} font-semibold leading-tight truncate">{{ $p->title }}</p>
        <p class="{{ $compact ? 'text-[11px]' : 'text-xs sm:text-sm' }} font-bold text-emerald-700 tabular-nums">Rp{{ number_format($p->price, 0, ',', '.') }}</p>
    </div>
</a>

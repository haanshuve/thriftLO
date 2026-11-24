{{-- Bintang 1-5 (hanya tampilan). Butuh: $rating (int|float), opsional $size (kelas teks) --}}
@php $filled = (int) round($rating); @endphp
<span class="{{ $size ?? 'text-base' }} leading-none whitespace-nowrap" role="img" aria-label="{{ rtrim(rtrim(number_format($rating, 1, ',', ''), '0'), ',') }} dari 5 bintang"><span class="text-amber-400">{{ str_repeat('★', $filled) }}</span><span class="text-slate-300">{{ str_repeat('★', 5 - $filled) }}</span></span>

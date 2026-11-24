{{-- Ikon SVG kategori (garis, mengikuti currentColor). Nama dari config thriftlo.category_icons. --}}
@props(['name' => 'box'])
@php
    $paths = [
        'shirt'  => 'M9 4 4.5 6.5l1.8 4L8 9.6V20h8V9.6l1.7.9 1.8-4L15 4a3 3 0 0 1-6 0Z',
        'shoe'   => 'M3 17v-5l4-.5 2.5-3.5 3 2 2 3 4.5 1A2 2 0 0 1 21 16v1H3Zm0 0v2h18v-2M9 12l1.5 1M11 10.5l1.5 1',
        'bag'    => 'M5 8h14l-1.2 12H6.2L5 8Zm4 0V6.5a3 3 0 0 1 6 0V8',
        'watch'  => 'M9 3h6l.8 3.6M9 3l-.8 3.6m0 10.8L9 21h6l.8-3.6M12 9.5V12l1.8 1.2M6.5 12a5.5 5.5 0 1 0 11 0 5.5 5.5 0 0 0-11 0',
        'camera' => 'M4 8.5h3.2L9 6h6l1.8 2.5H20V19H4V8.5Zm8 8a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z',
        'box'    => 'M4 7.5 12 4l8 3.5v9L12 20l-8-3.5v-9Zm0 0 8 3.5m0 0 8-3.5M12 11v9',
    ];
@endphp
<svg {{ $attributes->merge(['class' => 'w-5 h-5 shrink-0']) }} fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="{{ $paths[$name] ?? $paths['box'] }}"/></svg>

{{--
    Nama merek sebagai wordmark bergaya (hanya tipografi, BUKAN logo resmi merek).
    Warna aksen dan gaya huruf dipilih dari id merek supaya tiap merek konsisten di semua halaman.
    Pakai: <x-brand-wordmark :brand="$brand" size="md" />
    - size : sm (admin/daftar) | md (kartu beranda) | lg (judul halaman merek)
--}}
@props(['brand', 'size' => 'md'])

@php
    $palettes = [
        'bg-emerald-50 text-emerald-700 ring-emerald-100',
        'bg-sky-50 text-sky-700 ring-sky-100',
        'bg-rose-50 text-rose-700 ring-rose-100',
        'bg-amber-50 text-amber-800 ring-amber-100',
        'bg-violet-50 text-violet-700 ring-violet-100',
        'bg-slate-900 text-white ring-slate-800',
        'bg-teal-50 text-teal-700 ring-teal-100',
        'bg-orange-50 text-orange-700 ring-orange-100',
        'bg-indigo-50 text-indigo-700 ring-indigo-100',
    ];
    $styles = [
        'font-black tracking-tight',
        'font-black italic tracking-tighter',
        'font-extrabold uppercase tracking-widest',
        'font-black uppercase tracking-tight',
        'font-extrabold italic',
    ];
    $id = (int) $brand->id;
    $palette = $palettes[$id % count($palettes)];
    $style = $styles[$id % count($styles)];
    $name = $brand->name;
    $long = mb_strlen($name) > 10;

    [$box, $text] = match ($size) {
        'sm'    => ['w-12 h-12 rounded-lg', $long ? 'text-[8px]' : 'text-[10px]'],
        'lg'    => ['w-20 h-20 sm:w-24 sm:h-24 rounded-2xl', $long ? 'text-sm' : 'text-lg sm:text-xl'],
        default => ['w-full aspect-[4/3] rounded-xl', $long ? 'text-sm sm:text-base' : 'text-lg sm:text-xl'],
    };
@endphp

<span {{ $attributes->merge(['class' => "shrink-0 flex items-center justify-center text-center px-2 ring-1 ring-inset {$box} {$palette}"]) }} aria-hidden="true">
    <span class="leading-none break-words line-clamp-2 {{ $style }} {{ $text }}">{{ $name }}</span>
</span>

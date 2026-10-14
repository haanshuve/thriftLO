{{--
    Kartu angka panel admin.
    Pakai: <x-admin.stat label="Pengguna" :value="12" hint="..." :href="route(...)" tone="amber" />
    - tone : slate (default) | emerald | amber | rose | sky
--}}
@props(['label', 'value', 'hint' => null, 'href' => null, 'tone' => 'slate'])

@php
    $accent = match ($tone) {
        'emerald' => 'text-emerald-700',
        'amber'   => 'text-amber-700',
        'rose'    => 'text-rose-700',
        'sky'     => 'text-sky-700',
        default   => 'text-slate-900',
    };
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }} @if($href) href="{{ $href }}" @endif
    {{ $attributes->merge(['class' => 'block rounded-xl bg-white border border-slate-200 p-4 ' . ($href ? 'hover:border-emerald-400 hover:shadow-sm transition' : '')]) }}>
    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $label }}</p>
    <p class="mt-1 text-2xl font-bold tabular-nums {{ $accent }}">{{ $value }}</p>
    @if($hint)
        <p class="mt-0.5 text-xs text-slate-500">{{ $hint }}</p>
    @endif
</{{ $tag }}>

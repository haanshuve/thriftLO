{{--
    Foto profil bulat. Tanpa foto: lingkaran berisi inisial nama.
    Pakai: <x-avatar :user="$user" size="md" />
    - size : xs | sm | md | lg | xl | 2xl
    - name : nama untuk inisial (default nama user), mis. nama toko
    - tone : solid (hijau, teks putih) | soft (hijau muda)
    Dekoratif (aria-hidden) karena nama selalu ditulis di sebelahnya.
--}}
@props(['user', 'size' => 'md', 'name' => null, 'tone' => 'solid'])

@php
    $label = trim((string) ($name ?: $user?->name)) ?: '?';
    $initial = mb_strtoupper(mb_substr($label, 0, 1));
    $src = $user?->avatarUrl();

    $sizeClass = match ($size) {
        'xs'    => 'w-7 h-7 text-xs',
        'sm'    => 'w-9 h-9 text-xs',
        'lg'    => 'w-11 h-11 text-base',
        'xl'    => 'w-14 h-14 text-2xl',
        '2xl'   => 'w-16 h-16 text-2xl',
        default => 'w-10 h-10 text-sm',
    };
    $toneClass = $tone === 'soft' ? 'bg-emerald-100 text-emerald-700' : 'bg-emerald-600 text-white';
@endphp

<span {{ $attributes->merge(['class' => "relative shrink-0 inline-flex items-center justify-center overflow-hidden rounded-full font-bold {$sizeClass} {$toneClass}"]) }} aria-hidden="true" data-avatar>
    <span>{{ $initial }}</span>
    @if($src)
        {{-- Kalau foto gagal dimuat (mis. link Google kedaluwarsa), inisial di bawahnya tetap terlihat --}}
        <img src="{{ $src }}" alt="" loading="lazy" referrerpolicy="no-referrer" onerror="this.remove()"
             class="absolute inset-0 w-full h-full object-cover">
    @endif
</span>

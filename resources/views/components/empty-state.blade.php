@props(['title', 'illustration' => 'box'])

{{-- Empty state ramah: ilustrasi + judul + pesan + aksi opsional (slot "actions") --}}
<div {{ $attributes->merge(['class' => 'flex flex-col items-center text-center px-6 py-12 sm:py-16 rounded-2xl bg-gradient-to-b from-emerald-50/80 to-white border border-emerald-100 animate-fade-up']) }}>
    @switch($illustration)
        @case('search')
            <svg viewBox="0 0 160 120" class="w-36 h-28" aria-hidden="true">
                <ellipse cx="80" cy="110" rx="50" ry="5" fill="#d1fae5"/>
                <g transform="rotate(-12 64 66)">
                    <rect x="36" y="32" width="56" height="70" rx="10" fill="#a7f3d0" stroke="#059669" stroke-width="2.5"/>
                    <circle cx="64" cy="48" r="5" fill="#fff" stroke="#059669" stroke-width="2.5"/>
                    <path d="M48 70h32M48 82h22" stroke="#059669" stroke-width="2.5" stroke-linecap="round"/>
                </g>
                <circle cx="106" cy="62" r="19" fill="#ecfdf5" stroke="#047857" stroke-width="5"/>
                <path d="M120 76l15 15" stroke="#047857" stroke-width="7" stroke-linecap="round"/>
                <path d="M134 22v8M130 26h8M24 30v6M21 33h6" stroke="#f59e0b" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
            @break
        @case('chat')
            <svg viewBox="0 0 160 120" class="w-36 h-28" aria-hidden="true">
                <ellipse cx="80" cy="110" rx="50" ry="5" fill="#d1fae5"/>
                <path d="M30 30h62a10 10 0 0 1 10 10v26a10 10 0 0 1-10 10H54l-14 12V76H30a10 10 0 0 1-10-10V40a10 10 0 0 1 10-10Z" fill="#a7f3d0" stroke="#059669" stroke-width="2.5" stroke-linejoin="round"/>
                <path d="M68 50h62a10 10 0 0 1 10 10v24a10 10 0 0 1-10 10h-8v12L108 94H68a10 10 0 0 1-10-10V60a10 10 0 0 1 10-10Z" fill="#ecfdf5" stroke="#047857" stroke-width="2.5" stroke-linejoin="round"/>
                <circle cx="86" cy="72" r="3.5" fill="#047857"/><circle cx="99" cy="72" r="3.5" fill="#047857"/><circle cx="112" cy="72" r="3.5" fill="#047857"/>
                <path d="M138 24v8M134 28h8" stroke="#f59e0b" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
            @break
        @default
            <svg viewBox="0 0 160 120" class="w-36 h-28" aria-hidden="true">
                <ellipse cx="80" cy="110" rx="52" ry="5" fill="#d1fae5"/>
                <path d="M38 54 80 66 122 54v42l-42 12-42-12Z" fill="#a7f3d0" stroke="#059669" stroke-width="2.5" stroke-linejoin="round"/>
                <path d="M80 66v42" stroke="#059669" stroke-width="2.5"/>
                <path d="M38 54 24 40l42-12 14 14ZM122 54l14-14-42-12-14 14Z" fill="#ecfdf5" stroke="#059669" stroke-width="2.5" stroke-linejoin="round"/>
                <path d="M80 18a5 5 0 1 0-5-5M80 18v5l20 13H60l20-13" fill="none" stroke="#047857" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M132 22v8M128 26h8M26 26v6M23 29h6" stroke="#f59e0b" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
    @endswitch

    <h3 class="mt-4 text-base sm:text-lg font-bold text-slate-900">{{ $title }}</h3>
    <p class="mt-1 text-sm text-slate-600 max-w-sm">{{ $slot }}</p>

    @isset($actions)
        <div class="mt-5 flex flex-wrap justify-center gap-2">{{ $actions }}</div>
    @endisset
</div>

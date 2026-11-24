{{--
    Mega menu kategori di header marketplace.
    - Desktop (lg+): baris grup di bawah pencarian; hover/fokus membuka panel lebar penuh berisi kategori
      (kiri) dan subkategori yang bisa difilter (kanan). Perilaku di resources/js/mega-menu.js.
    - Ponsel/tablet: laci dari tombol hamburger (lihat layouts/market) dengan accordion per grup.
    Semua tautan menuju katalog terfilter: ?kategori=, ?kategori=&merek=, ?kategori=&koleksi=.
    Butuh: $groups (App\Support\CategoryMenu::groups()), $activeKategori, $mobile (bool).
--}}
@props(['groups', 'activeKategori' => 'all', 'mobile' => false])

@php
    $catUrl = fn (string $slug, array $extra = []) => route('home', ['kategori' => $slug, ...$extra]);
    $count = fn (int $n) => $n > 0 ? number_format($n, 0, ',', '.') : null;
    $collections = \App\Support\CategoryMenu::COLLECTIONS;
@endphp

@if($mobile)
    {{-- ===================== Ponsel/tablet: accordion per grup ===================== --}}
    <div class="divide-y divide-slate-100" data-category-menu="mobile">
        @foreach($groups as $g)
            <details class="group/acc" @if($loop->first) open @endif>
                <summary class="flex items-center justify-between gap-2 px-4 py-3 cursor-pointer list-none font-semibold text-slate-900 hover:bg-slate-50 [&::-webkit-details-marker]:hidden">
                    {{ $g['label'] }}
                    <svg class="w-4 h-4 text-slate-400 transition group-open/acc:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                </summary>
                <ul class="pb-2">
                    @foreach($g['categories'] as $c)
                        <li>
                            <details class="group/cat">
                                <summary class="flex items-center gap-3 pl-6 pr-4 py-2.5 cursor-pointer list-none text-sm text-slate-700 hover:bg-emerald-50 [&::-webkit-details-marker]:hidden {{ $activeKategori === $c['slug'] ? 'text-emerald-800 font-semibold' : '' }}">
                                    <x-category-icon :name="$c['icon']" class="w-5 h-5 text-emerald-700" />
                                    <span class="flex-1">{{ $c['label'] }}</span>
                                    @if($count($c['count']))<span class="text-xs text-slate-400 tabular-nums">{{ $count($c['count']) }}</span>@endif
                                    <svg class="w-4 h-4 text-slate-400 transition group-open/cat:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                                </summary>
                                <ul class="pl-14 pr-4 pb-2 space-y-0.5 text-sm">
                                    <li><a href="{{ $catUrl($c['slug']) }}" data-menu-link class="block py-1.5 font-semibold text-emerald-700 hover:underline">Semua {{ $c['label'] }}</a></li>
                                    @foreach($c['brands'] as $b)
                                        <li><a href="{{ $catUrl($c['slug'], ['merek' => $b['slug']]) }}" data-menu-link class="flex justify-between py-1.5 text-slate-600 hover:text-emerald-700">{{ $b['name'] }} <span class="text-xs text-slate-400 tabular-nums">{{ $count($b['count']) }}</span></a></li>
                                    @endforeach
                                    @foreach($collections as $key => $label)
                                        <li><a href="{{ $catUrl($c['slug'], ['koleksi' => $key]) }}" data-menu-link class="block py-1.5 text-slate-600 hover:text-emerald-700">{{ $label }}</a></li>
                                    @endforeach
                                </ul>
                            </details>
                        </li>
                    @endforeach
                </ul>
            </details>
        @endforeach
    </div>
@else
    {{-- ===================== Desktop: mega menu ===================== --}}
    <nav aria-label="Kategori" class="hidden lg:block relative border-t border-slate-100" data-category-menu="desktop"
         x-data="megaMenu" @mouseleave="scheduleClose()" @mouseenter="cancelClose()"
         @keydown.escape="closeAndFocus()" @click.outside="close()" @focusout="onFocusOut($event)">
        <div class="max-w-7xl mx-auto px-6 flex items-center">
            <ul class="flex items-center gap-1" role="list">
                @foreach($groups as $g)
                    @php $hasActive = collect($g['categories'])->contains('slug', $activeKategori); @endphp
                    <li>
                        <button type="button" id="mm-btn-{{ $g['key'] }}" data-group-btn="{{ $g['key'] }}"
                                aria-haspopup="true" aria-expanded="false" aria-controls="mm-panel-{{ $g['key'] }}"
                                :aria-expanded="(open === @js($g['key'])).toString()"
                                @mouseenter="hoverOpen(@js($g['key']))" @focus="focusOpen(@js($g['key']))" @click="openNow(@js($g['key']))"
                                @keydown.enter.prevent="openNow(@js($g['key']), true)" @keydown.space.prevent="openNow(@js($g['key']), true)"
                                @keydown.arrow-down.prevent="openNow(@js($g['key']), true)"
                                @keydown.arrow-right.prevent="moveGroup($event, 1)" @keydown.arrow-left.prevent="moveGroup($event, -1)"
                                class="inline-flex items-center gap-1 h-11 px-3 -mb-px border-b-2 text-sm font-semibold transition focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-emerald-600 {{ $hasActive ? 'border-emerald-600 text-emerald-800' : 'border-transparent text-slate-600 hover:text-emerald-700' }}"
                                :class="open === @js($g['key']) ? '!border-emerald-600 !text-emerald-800' : ''">
                            {{ $g['label'] }}
                            <svg class="w-3.5 h-3.5 text-slate-400 transition" :class="open === @js($g['key']) && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                        </button>
                    </li>
                @endforeach
            </ul>
            <a href="{{ route('home', ['koleksi' => 'baru']) }}" class="ml-auto h-11 inline-flex items-center px-3 text-sm font-semibold text-slate-600 hover:text-emerald-700">Semua barang</a>
            <a href="{{ route('brands.index') }}" class="h-11 inline-flex items-center px-3 text-sm font-semibold text-slate-600 hover:text-emerald-700">Merek</a>
        </div>

        @foreach($groups as $g)
            @php $first = collect($g['categories'])->firstWhere('slug', $activeKategori) ?? $g['categories'][0]; @endphp
            <div id="mm-panel-{{ $g['key'] }}" role="region" aria-labelledby="mm-btn-{{ $g['key'] }}" data-group-panel="{{ $g['key'] }}"
                 x-show="open === @js($g['key'])" style="display: none"
                 x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                 x-data="{ cat: @js($first['slug']) }"
                 class="absolute inset-x-0 top-full z-50 bg-white border-y border-emerald-100 shadow-xl shadow-emerald-950/10">
                <div class="max-w-7xl mx-auto px-6 py-5 grid grid-cols-[15rem_minmax(0,1fr)] gap-8">
                    {{-- Kiri: kategori dalam grup --}}
                    <ul class="space-y-0.5 border-r border-slate-100 pr-4" role="list">
                        @foreach($g['categories'] as $c)
                            <li>
                                <a href="{{ $catUrl($c['slug']) }}" data-cat-link="{{ $c['slug'] }}" data-menu-link
                                   @mouseenter="cat = @js($c['slug'])" @focus="cat = @js($c['slug'])"
                                   @keydown.arrow-down.prevent="stepCategory($event, 1)" @keydown.arrow-up.prevent="stepCategory($event, -1)"
                                   @keydown.arrow-right.prevent="enterSubmenu($event)"
                                   class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-semibold transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-emerald-600"
                                   :class="cat === @js($c['slug']) ? 'bg-emerald-50 text-emerald-800' : 'text-slate-700 hover:bg-slate-50'"
                                   @if($activeKategori === $c['slug']) aria-current="page" @endif>
                                    <x-category-icon :name="$c['icon']" class="w-5 h-5 text-emerald-700" />
                                    <span class="flex-1">{{ $c['label'] }}</span>
                                    @if($count($c['count']))<span class="text-xs font-normal text-slate-400 tabular-nums">{{ $count($c['count']) }}</span>@endif
                                    <svg class="w-4 h-4 text-slate-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                                </a>
                            </li>
                        @endforeach
                    </ul>

                    {{-- Kanan: subkategori kategori yang sedang ditunjuk --}}
                    @foreach($g['categories'] as $c)
                        <div data-cat-panel="{{ $c['slug'] }}" x-show="cat === @js($c['slug'])" @if($c['slug'] !== $first['slug']) style="display: none" @endif
                             @keydown.arrow-left.prevent="backToCategory($event)" @keydown.arrow-down.prevent="stepLink($event, 1)" @keydown.arrow-up.prevent="stepLink($event, -1)">
                            <div class="flex items-baseline justify-between gap-4">
                                <p class="text-base font-bold text-slate-900">{{ $c['label'] }}</p>
                                <a href="{{ $catUrl($c['slug']) }}" data-menu-link class="text-sm font-semibold text-emerald-700 hover:underline">
                                    Semua {{ $c['label'] }}@if($count($c['count'])) ({{ $count($c['count']) }})@endif →
                                </a>
                            </div>

                            <p class="mt-4 text-[11px] font-bold uppercase tracking-wider text-slate-400">Merek</p>
                            <ul class="mt-2 grid grid-cols-3 xl:grid-cols-4 gap-x-6 gap-y-1 text-sm" role="list">
                                <li><a href="{{ $catUrl($c['slug']) }}" data-menu-link class="flex items-center justify-between gap-2 py-1 font-semibold text-emerald-700 hover:underline">Semua</a></li>
                                @foreach($c['brands'] as $b)
                                    <li>
                                        <a href="{{ $catUrl($c['slug'], ['merek' => $b['slug']]) }}" data-menu-link class="flex items-center justify-between gap-2 py-1 text-slate-600 hover:text-emerald-700">
                                            <span class="truncate">{{ $b['name'] }}</span>
                                            <span class="text-xs text-slate-400 tabular-nums">{{ $count($b['count']) }}</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                            @if(empty($c['brands']))
                                <p class="mt-1 text-xs text-slate-400">Belum ada barang bermerek di kategori ini.</p>
                            @endif

                            <p class="mt-4 text-[11px] font-bold uppercase tracking-wider text-slate-400">Cara beli</p>
                            <ul class="mt-2 grid grid-cols-3 xl:grid-cols-4 gap-x-6 gap-y-1 text-sm" role="list">
                                @foreach($collections as $key => $label)
                                    <li><a href="{{ $catUrl($c['slug'], ['koleksi' => $key]) }}" data-menu-link class="block py-1 text-slate-600 hover:text-emerald-700">{{ $label }}</a></li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </nav>
@endif

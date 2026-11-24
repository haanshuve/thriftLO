{{--
    Hero beranda: carousel 6 slide (fitur nyata thriftLO + barang terbaru dari database) dan 2 banner
    kecil di samping (desktop). Ilustrasi murni SVG/gradien, tanpa gambar eksternal; satu-satunya foto
    adalah foto utama barang asli di slide "Barang terbaru".
    Butuh: $sellerCta = [url|null, label], $latestProducts (Collection<Product>, maks. 4).
    Geser otomatis tiap 5 detik, berhenti saat di-hover/fokus/disentuh, mati bila prefers-reduced-motion.
--}}
@php
    [$sellerUrl, $sellerLabel] = $sellerCta;
    $latestProducts = $latestProducts ?? collect();
    $contactEmail = config('thriftlo.contact_email');

    $slides = array_values(array_filter([
        [
            'title'    => 'Lihat kondisi asli lewat Video Proof',
            'subtitle' => 'Penjual merekam barangnya sendiri, jadi kamu bisa cek noda, jahitan, dan fungsinya sebelum beli.',
            'cta'      => ['Lihat barang ber-video', route('home', ['koleksi' => 'video'])],
            'bg'       => 'from-emerald-700 via-emerald-600 to-teal-500',
            'button'   => 'bg-white text-emerald-800 hover:bg-emerald-50',
            'art'      => 'video',
        ],
        [
            'title'    => 'Belanja dari penjual terverifikasi',
            'subtitle' => 'Setiap penjual lolos cek KTP dan selfie oleh tim thriftLO sebelum boleh menayangkan barang.',
            'cta'      => ['Jelajahi katalog', route('home', ['koleksi' => 'baru'])],
            'bg'       => 'from-teal-800 via-teal-700 to-emerald-600',
            'button'   => 'bg-white text-teal-800 hover:bg-teal-50',
            'art'      => 'verified',
        ],
        [
            'title'    => 'COD aman: booking, ketemuan, scan QR',
            'subtitle' => 'Kunci barang dengan booking, ketemuan di titik yang adil untuk kedua pihak di Batam, lalu selesaikan dengan scan QR.',
            'cta'      => ['Cari barang siap COD', route('home', ['koleksi' => 'cod'])],
            'bg'       => 'from-emerald-800 via-teal-700 to-cyan-600',
            'button'   => 'bg-amber-300 text-amber-950 hover:bg-amber-200',
            'art'      => 'qr',
        ],
        $latestProducts->count() >= 3 ? [
            'title'    => 'Barang terbaru',
            'subtitle' => 'Baru saja ditayangkan penjual thriftLO.',
            'cta'      => ['Lihat semua barang', route('home', ['koleksi' => 'baru'])],
            'bg'       => 'from-green-700 via-emerald-600 to-teal-600',
            'button'   => 'bg-white text-emerald-800 hover:bg-emerald-50',
            'art'      => 'products',
        ] : null,
        [
            'title'    => 'Belum nemu? Pakai One to Buy',
            'subtitle' => 'Tulis barang yang kamu cari, biar penjual yang datang menawarkan.',
            'cta'      => ['Buat request barang', route('requests.index')],
            'bg'       => 'from-teal-600 via-emerald-600 to-lime-600',
            'button'   => 'bg-slate-900 text-white hover:bg-slate-800',
            'art'      => 'request',
        ],
        [
            'title'    => 'Jual barang preloved kamu',
            'subtitle' => 'Daftar, verifikasi KTP, lalu tayangkan barangmu. Gratis untuk ' . (int) config('thriftlo.subscription.free_product_limit') . ' barang pertama.',
            'cta'      => [$sellerLabel, $sellerUrl ?? '#jadi-penjual'],
            'bg'       => 'from-emerald-900 via-emerald-700 to-teal-600',
            'button'   => 'bg-amber-300 text-amber-950 hover:bg-amber-200',
            'art'      => 'store',
            'note'     => true,
        ],
    ]));
@endphp

<script>
    // Carousel hero: scroll-snap untuk geser manual (sentuh/trackpad), Alpine untuk autoplay & navigasi
    window.heroCarousel = function (count) {
        return {
            index: 0,
            count: count,
            paused: false,
            timer: null,
            resumeTimer: null,
            reduceMotion: window.matchMedia('(prefers-reduced-motion: reduce)').matches,
            init() {
                this.start();
                let settle;
                this.$refs.track.addEventListener('scroll', () => {
                    clearTimeout(settle);
                    settle = setTimeout(() => {
                        this.index = Math.round(this.$refs.track.scrollLeft / this.$refs.track.clientWidth);
                    }, 80);
                }, { passive: true });
                document.addEventListener('visibilitychange', () => document.hidden ? this.stop() : this.start());
            },
            start() {
                if (this.reduceMotion) return;
                this.stop();
                this.timer = setInterval(() => { if (!this.paused) this.go(this.index + 1); }, 5000);
            },
            stop() { clearInterval(this.timer); },
            // Disentuh: berhenti, lalu lanjut beberapa detik setelah jari dilepas
            touchStart() { clearTimeout(this.resumeTimer); this.paused = true; },
            touchEnd() { clearTimeout(this.resumeTimer); this.resumeTimer = setTimeout(() => { this.paused = false; }, 4000); },
            go(target) {
                this.index = (target + this.count) % this.count;
                this.$refs.track.scrollTo({ left: this.index * this.$refs.track.clientWidth, behavior: this.reduceMotion ? 'auto' : 'smooth' });
            },
        };
    };
</script>

<section aria-label="Sorotan thriftLO" class="grid lg:grid-cols-3 gap-3 sm:gap-4">
    <!-- Carousel -->
    <div class="relative lg:col-span-2 rounded-2xl overflow-hidden shadow-lg shadow-emerald-900/10"
         x-data="heroCarousel({{ count($slides) }})"
         @mouseenter="paused = true" @mouseleave="paused = false"
         @focusin="paused = true" @focusout="paused = false"
         @touchstart.passive="touchStart()" @touchend.passive="touchEnd()"
         @keydown.arrow-left="go(index - 1)" @keydown.arrow-right="go(index + 1)"
         aria-roledescription="carousel">
        {{-- Tinggi tetap supaya halaman tidak melompat saat slide berganti --}}
        <div x-ref="track" class="flex h-[17.5rem] sm:h-[17rem] lg:h-[20rem] overflow-x-auto snap-x snap-mandatory no-scrollbar scroll-smooth motion-reduce:scroll-auto" tabindex="-1">
            @foreach($slides as $i => $slide)
                <div class="relative w-full h-full shrink-0 snap-start overflow-hidden bg-gradient-to-br {{ $slide['bg'] }} text-white"
                     data-slide="{{ $slide['art'] }}"
                     role="group" aria-roledescription="slide" aria-label="{{ $i + 1 }} dari {{ count($slides) }}: {{ $slide['title'] }}">
                    {{-- Pola dekoratif --}}
                    <svg class="absolute inset-0 w-full h-full opacity-[0.12]" aria-hidden="true">
                        <defs><pattern id="dots-{{ $i }}" width="22" height="22" patternUnits="userSpaceOnUse"><circle cx="2" cy="2" r="1.6" fill="#fff"/></pattern></defs>
                        <rect width="100%" height="100%" fill="url(#dots-{{ $i }})"/>
                    </svg>
                    <div class="absolute -right-10 -bottom-16 w-56 h-56 rounded-full bg-white/10" aria-hidden="true"></div>

                    <div class="relative h-full flex items-center gap-4 sm:gap-6 px-5 pt-5 pb-9 sm:px-14 sm:pt-7 sm:pb-10">
                        <div class="{{ $slide['art'] === 'products' ? 'w-full sm:w-auto sm:max-w-[13rem] lg:max-w-[14rem] shrink-0' : 'flex-1 min-w-0' }}">
                            <h2 class="text-xl sm:text-3xl font-black leading-tight tracking-tight">{{ $slide['title'] }}</h2>
                            <p class="mt-2 text-sm sm:text-base text-white/90 max-w-md {{ $slide['art'] === 'products' ? 'hidden sm:block' : '' }}">{{ $slide['subtitle'] }}</p>

                            @if($slide['art'] === 'products')
                                {{-- Ponsel: kartu barang di bawah judul --}}
                                <div class="sm:hidden mt-3 grid grid-cols-3 gap-2">
                                    @foreach($latestProducts->take(3) as $p)
                                        @include('home.partials.hero-product', ['p' => $p, 'compact' => true])
                                    @endforeach
                                </div>
                            @endif

                            <a href="{{ $slide['cta'][1] }}"
                               class="{{ $slide['art'] === 'products' ? 'mt-3 sm:mt-4' : 'mt-4' }} inline-flex items-center gap-1.5 h-10 px-4 rounded-lg text-sm font-bold shadow-sm transition active:scale-95 motion-reduce:transform-none focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white {{ $slide['button'] }}">
                                {{ $slide['cta'][0] }}
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                            </a>

                            @if(!empty($slide['note']) && $contactEmail)
                                <p class="mt-2 text-xs text-white/80">
                                    Ingin beriklan di ThriftLO?
                                    <a href="mailto:{{ $contactEmail }}?subject={{ rawurlencode('Beriklan di ThriftLO') }}" class="font-semibold underline underline-offset-2 hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-white">Hubungi kami</a>
                                </p>
                            @endif
                        </div>

                        @if($slide['art'] === 'products')
                            {{-- Tablet/desktop: kartu barang di samping teks --}}
                            <div class="hidden sm:grid flex-1 min-w-0 grid-cols-3 lg:grid-cols-4 gap-3">
                                @foreach($latestProducts as $j => $p)
                                    <div class="{{ $j === 3 ? 'hidden lg:block' : '' }}">
                                        @include('home.partials.hero-product', ['p' => $p, 'compact' => false])
                                    </div>
                                @endforeach
                            </div>
                        @else
                            {{-- Ilustrasi (disembunyikan di layar kecil) --}}
                            <div class="hidden sm:block w-32 lg:w-40 shrink-0" aria-hidden="true">
                                @switch($slide['art'])
                                    @case('video')
                                        <svg viewBox="0 0 120 120" class="w-full h-auto drop-shadow-lg">
                                            <rect x="30" y="8" width="60" height="104" rx="12" fill="#064e3b"/>
                                            <rect x="35" y="18" width="50" height="84" rx="6" fill="#ecfdf5"/>
                                            <path d="M45 74 58 52l9 13 6-8 10 17Z" fill="#6ee7b7"/>
                                            <circle cx="72" cy="40" r="6" fill="#fbbf24"/>
                                            <circle cx="60" cy="60" r="15" fill="#047857" opacity=".92"/>
                                            <path d="m55 52 13 8-13 8Z" fill="#fff"/>
                                            <circle cx="96" cy="22" r="10" fill="#f43f5e"/><circle cx="96" cy="22" r="4" fill="#fff"/>
                                        </svg>
                                        @break
                                    @case('verified')
                                        <svg viewBox="0 0 120 120" class="w-full h-auto drop-shadow-lg">
                                            <rect x="10" y="30" width="78" height="52" rx="8" fill="#fff"/>
                                            <rect x="10" y="30" width="78" height="12" rx="6" fill="#99f6e4"/>
                                            <circle cx="32" cy="62" r="10" fill="#ccfbf1"/><path d="M22 78c2-7 7-10 10-10s8 3 10 10Z" fill="#5eead4"/>
                                            <rect x="50" y="54" width="28" height="5" rx="2.5" fill="#cbd5e1"/><rect x="50" y="64" width="20" height="5" rx="2.5" fill="#e2e8f0"/>
                                            <path d="M88 52 108 60v14c0 13-9 21-20 25-11-4-20-12-20-25V60Z" fill="#0f766e" stroke="#fff" stroke-width="4"/>
                                            <path d="m80 75 6 6 11-12" fill="none" stroke="#fff" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                        @break
                                    @case('qr')
                                        <svg viewBox="0 0 120 120" class="w-full h-auto drop-shadow-lg">
                                            <rect x="14" y="20" width="80" height="80" rx="12" fill="#fff"/>
                                            <g fill="#064e3b">
                                                <rect x="24" y="30" width="20" height="20" rx="3"/><rect x="64" y="30" width="20" height="20" rx="3"/><rect x="24" y="70" width="20" height="20" rx="3"/>
                                                <rect x="29" y="35" width="10" height="10" rx="2" fill="#fff"/><rect x="69" y="35" width="10" height="10" rx="2" fill="#fff"/><rect x="29" y="75" width="10" height="10" rx="2" fill="#fff"/>
                                                <rect x="52" y="58" width="7" height="7"/><rect x="64" y="62" width="7" height="7"/><rect x="76" y="58" width="8" height="7"/>
                                                <rect x="52" y="72" width="7" height="10"/><rect x="66" y="76" width="7" height="7"/><rect x="78" y="72" width="6" height="18"/>
                                            </g>
                                            <rect x="8" y="58" width="92" height="4" rx="2" fill="#fbbf24"/>
                                            <path d="M100 8c-9 0-15 7-15 15 0 11 15 25 15 25s15-14 15-25c0-8-6-15-15-15Z" fill="#f43f5e" stroke="#fff" stroke-width="3"/>
                                            <circle cx="100" cy="23" r="5" fill="#fff"/>
                                        </svg>
                                        @break
                                    @case('request')
                                        <svg viewBox="0 0 120 120" class="w-full h-auto drop-shadow-lg">
                                            <path d="M14 22h70a10 10 0 0 1 10 10v34a10 10 0 0 1-10 10H42l-18 14V76H14A10 10 0 0 1 4 66V32a10 10 0 0 1 10-10Z" fill="#fff"/>
                                            <rect x="18" y="36" width="52" height="6" rx="3" fill="#86efac"/><rect x="18" y="50" width="38" height="6" rx="3" fill="#d9f99d"/>
                                            <circle cx="84" cy="80" r="20" fill="#14532d" stroke="#fff" stroke-width="5"/>
                                            <path d="m98 94 14 14" stroke="#fff" stroke-width="8" stroke-linecap="round"/>
                                            <path d="m76 80 6 6 10-11" fill="none" stroke="#bef264" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                        @break
                                    @default
                                        <svg viewBox="0 0 120 120" class="w-full h-auto drop-shadow-lg">
                                            <rect x="18" y="48" width="84" height="58" rx="6" fill="#fff"/>
                                            <path d="M14 30h92l-6 24H20Z" fill="#064e3b"/>
                                            <path d="M14 54c0 8 7 12 15 12s15-4 15-12c0 8 7 12 16 12s16-4 16-12c0 8 7 12 15 12s15-4 15-12Z" fill="#10b981"/>
                                            <rect x="30" y="74" width="26" height="32" rx="3" fill="#fde68a"/>
                                            <rect x="66" y="74" width="26" height="18" rx="3" fill="#a7f3d0"/>
                                            <circle cx="98" cy="24" r="14" fill="#fff"/><path d="m91 24 5 5 9-10" fill="none" stroke="#059669" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                @endswitch
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Panah -->
        <button type="button" @click="go(index - 1)" class="hidden sm:flex absolute left-3 top-1/2 -translate-y-1/2 w-9 h-9 items-center justify-center rounded-full bg-white/85 text-slate-800 shadow hover:bg-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-white" aria-label="Slide sebelumnya">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
        </button>
        <button type="button" @click="go(index + 1)" class="hidden sm:flex absolute right-3 top-1/2 -translate-y-1/2 w-9 h-9 items-center justify-center rounded-full bg-white/85 text-slate-800 shadow hover:bg-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-white" aria-label="Slide berikutnya">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
        </button>

        <!-- Titik indikator -->
        <div class="absolute bottom-3 inset-x-0 flex justify-center gap-1.5">
            @foreach($slides as $i => $slide)
                <button type="button" @click="go({{ $i }})"
                        class="h-2 rounded-full transition-all focus-visible:outline focus-visible:outline-2 focus-visible:outline-white"
                        :class="index === {{ $i }} ? 'w-6 bg-white' : 'w-2 bg-white/50 hover:bg-white/80'"
                        :aria-current="index === {{ $i }} ? 'true' : 'false'"
                        aria-label="Tampilkan slide {{ $i + 1 }}"></button>
            @endforeach
        </div>
    </div>

    <!-- Banner kecil (desktop) -->
    <div class="hidden lg:grid grid-rows-2 gap-4">
        <a href="{{ route('requests.index') }}" class="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-amber-100 to-orange-200 p-5 flex flex-col justify-between shadow-sm transition hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-amber-600">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-wider text-orange-700">One to Buy</p>
                <h2 class="mt-1 text-lg font-black text-slate-900 leading-snug">Request barang</h2>
                <p class="mt-1 text-sm text-slate-700">Belum nemu? Tulis barang yang kamu cari, biar penjual yang menawarkan.</p>
            </div>
            <span class="text-sm font-bold text-orange-800 group-hover:underline">Buat request →</span>
            <svg class="absolute -right-3 -bottom-3 w-24 h-24 text-orange-300/70" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
        </a>
        <a href="{{ $sellerUrl ?? '#jadi-penjual' }}" class="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-emerald-50 to-emerald-200 p-5 flex flex-col justify-between shadow-sm transition hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-emerald-600">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-wider text-emerald-700">Untuk penjual</p>
                <h2 class="mt-1 text-lg font-black text-slate-900 leading-snug">Jadi penjual terverifikasi</h2>
                <p class="mt-1 text-sm text-slate-700">Verifikasi KTP sekali, lalu jualan dengan lencana terpercaya.</p>
            </div>
            <span class="text-sm font-bold text-emerald-800 group-hover:underline">{{ $sellerLabel }} →</span>
            <svg class="absolute -right-3 -bottom-3 w-24 h-24 text-emerald-300/70" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 4 6v6c0 4.5 3.4 8.3 8 9 4.6-.7 8-4.5 8-9V6l-8-3Z"/><path d="m9 12 2 2 4-4"/></svg>
        </a>
    </div>
</section>

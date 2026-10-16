{{--
    Hero beranda: carousel 4 slide (fitur nyata thriftLO) + 2 banner kecil di samping (desktop).
    Ilustrasi murni SVG/gradien, tanpa gambar eksternal. Butuh: $sellerCta = [url|null, label].
    Geser otomatis tiap 5 detik, berhenti saat di-hover/fokus, mati bila prefers-reduced-motion.
--}}
@php
    [$sellerUrl, $sellerLabel] = $sellerCta;
    $slides = [
        [
            'title'    => 'Lihat kondisi asli lewat Video Proof',
            'subtitle' => 'Penjual merekam barangnya sendiri. Cek noda, jahitan, dan fungsi sebelum kamu beli.',
            'cta'      => ['Lihat barang ber-video', route('home', ['koleksi' => 'video'])],
            'bg'       => 'from-emerald-700 via-emerald-600 to-teal-500',
            'button'   => 'bg-white text-emerald-800 hover:bg-emerald-50',
            'art'      => 'video',
        ],
        [
            'title'    => 'COD aman pakai Smart QR',
            'subtitle' => 'Ketemuan dengan penjual di Batam, cek barangnya, bayar di tempat. Transaksi selesai setelah token QR dipindai.',
            'cta'      => ['Cari barang siap COD', route('home', ['koleksi' => 'cod'])],
            'bg'       => 'from-teal-700 via-emerald-700 to-emerald-600',
            'button'   => 'bg-amber-300 text-amber-950 hover:bg-amber-200',
            'art'      => 'qr',
        ],
        [
            'title'    => 'Belanja dari seluruh Indonesia',
            'subtitle' => 'Uangmu ditahan aman lewat escrow dan baru diteruskan ke penjual setelah barang kamu terima.',
            'cta'      => ['Lihat barang bisa kirim', route('home', ['koleksi' => 'kirim'])],
            'bg'       => 'from-sky-700 via-sky-600 to-emerald-500',
            'button'   => 'bg-white text-sky-800 hover:bg-sky-50',
            'art'      => 'escrow',
        ],
        [
            'title'    => 'Punya barang nganggur? Jadi penjual',
            'subtitle' => 'Daftar, verifikasi KTP, lalu tayangkan barangmu. Gratis untuk ' . (int) config('thriftlo.subscription.free_product_limit') . ' barang pertama.',
            'cta'      => [$sellerLabel, $sellerUrl ?? '#jadi-penjual'],
            'bg'       => 'from-orange-600 via-amber-500 to-amber-400',
            'button'   => 'bg-slate-900 text-white hover:bg-slate-800',
            'art'      => 'store',
        ],
    ];
@endphp

<script>
    // Carousel hero: scroll-snap untuk geser manual (sentuh/trackpad), Alpine untuk autoplay & navigasi
    window.heroCarousel = function (count) {
        return {
            index: 0,
            count: count,
            paused: false,
            timer: null,
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
         aria-roledescription="carousel">
        <div x-ref="track" class="flex overflow-x-auto snap-x snap-mandatory no-scrollbar scroll-smooth motion-reduce:scroll-auto" tabindex="-1">
            @foreach($slides as $i => $slide)
                <div class="relative w-full shrink-0 snap-start bg-gradient-to-br {{ $slide['bg'] }} text-white"
                     role="group" aria-roledescription="slide" aria-label="{{ $i + 1 }} dari {{ count($slides) }}: {{ $slide['title'] }}">
                    {{-- Pola dekoratif --}}
                    <svg class="absolute inset-0 w-full h-full opacity-[0.12]" aria-hidden="true">
                        <defs><pattern id="dots-{{ $i }}" width="22" height="22" patternUnits="userSpaceOnUse"><circle cx="2" cy="2" r="1.6" fill="#fff"/></pattern></defs>
                        <rect width="100%" height="100%" fill="url(#dots-{{ $i }})"/>
                    </svg>
                    <div class="absolute -right-10 -bottom-16 w-56 h-56 rounded-full bg-white/10" aria-hidden="true"></div>

                    <div class="relative flex items-center gap-4 px-5 py-6 sm:px-10 sm:py-9 min-h-[12.5rem] sm:min-h-[16rem] lg:min-h-[17.5rem]">
                        <div class="flex-1 min-w-0">
                            <h2 class="text-lg sm:text-3xl font-black leading-tight tracking-tight">{{ $slide['title'] }}</h2>
                            <p class="mt-2 text-xs sm:text-base text-white/90 max-w-md line-clamp-3">{{ $slide['subtitle'] }}</p>
                            <a href="{{ $slide['cta'][1] }}"
                               class="mt-4 inline-flex items-center gap-1.5 h-10 px-4 rounded-lg text-sm font-bold shadow-sm transition active:scale-95 motion-reduce:transform-none focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white {{ $slide['button'] }}">
                                {{ $slide['cta'][0] }}
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                            </a>
                        </div>

                        {{-- Ilustrasi --}}
                        <div class="w-24 sm:w-40 shrink-0" aria-hidden="true">
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
                                @case('qr')
                                    <svg viewBox="0 0 120 120" class="w-full h-auto drop-shadow-lg">
                                        <rect x="14" y="14" width="92" height="92" rx="14" fill="#fff"/>
                                        <g fill="#064e3b">
                                            <rect x="26" y="26" width="24" height="24" rx="4"/><rect x="70" y="26" width="24" height="24" rx="4"/><rect x="26" y="70" width="24" height="24" rx="4"/>
                                            <rect x="32" y="32" width="12" height="12" rx="2" fill="#fff"/><rect x="76" y="32" width="12" height="12" rx="2" fill="#fff"/><rect x="32" y="76" width="12" height="12" rx="2" fill="#fff"/>
                                            <rect x="58" y="58" width="8" height="8"/><rect x="70" y="62" width="8" height="8"/><rect x="82" y="58" width="12" height="8"/>
                                            <rect x="58" y="74" width="8" height="12"/><rect x="74" y="78" width="8" height="8"/><rect x="86" y="74" width="8" height="20"/><rect x="58" y="90" width="20" height="4"/>
                                        </g>
                                        <rect x="8" y="56" width="104" height="5" rx="2.5" fill="#fbbf24"/>
                                    </svg>
                                    @break
                                @case('escrow')
                                    <svg viewBox="0 0 120 120" class="w-full h-auto drop-shadow-lg">
                                        <path d="M20 46 60 28l40 18v42L60 106 20 88Z" fill="#fde68a"/>
                                        <path d="M60 64v42L20 88V46Z" fill="#f59e0b"/><path d="M20 46 60 64l40-18" fill="none" stroke="#b45309" stroke-width="3"/>
                                        <path d="M60 10 84 20v16c0 15-10 25-24 30-14-5-24-15-24-30V20Z" fill="#0369a1" stroke="#fff" stroke-width="4"/>
                                        <path d="m50 38 7 7 13-14" fill="none" stroke="#fff" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                    @break
                                @default
                                    <svg viewBox="0 0 120 120" class="w-full h-auto drop-shadow-lg">
                                        <rect x="18" y="48" width="84" height="58" rx="6" fill="#fff"/>
                                        <path d="M14 30h92l-6 24H20Z" fill="#064e3b"/>
                                        <path d="M14 54c0 8 7 12 15 12s15-4 15-12c0 8 7 12 16 12s16-4 16-12c0 8 7 12 15 12s15-4 15-12Z" fill="#10b981"/>
                                        <rect x="30" y="74" width="26" height="32" rx="3" fill="#fed7aa"/>
                                        <rect x="66" y="74" width="26" height="18" rx="3" fill="#a7f3d0"/>
                                        <circle cx="98" cy="24" r="14" fill="#fff"/><path d="m91 24 5 5 9-10" fill="none" stroke="#059669" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                            @endswitch
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Panah (desktop) -->
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

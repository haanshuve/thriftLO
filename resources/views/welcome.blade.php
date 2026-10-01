<x-market-layout :show-categories="true">

    @php
        $activeKategori = request('kategori', 'all');
    @endphp

    <main class="max-w-7xl mx-auto px-4 sm:px-6 py-4 sm:py-6">

        <!-- Banner ringkas -->
        @if(!request('search') && $activeKategori === 'all')
            <section class="mb-4 sm:mb-6 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 text-white px-4 py-3 sm:px-8 sm:py-6 flex items-center justify-between gap-3 sm:gap-4">
                <div class="min-w-0">
                    <h1 class="text-sm sm:text-2xl font-black leading-tight">Preloved berkualitas, COD aman di Batam.</h1>
                    <p class="hidden sm:block text-sm text-emerald-50 mt-1">Chat penjual, tawar harga, lalu kunci barang dengan token QR.</p>
                </div>
                <div class="flex gap-3 sm:gap-6 shrink-0 text-right sm:text-left">
                    <div>
                        <p class="text-base sm:text-2xl font-black tabular-nums">{{ number_format($wastePreventedKg ?? 0, 1, ',', '.') }}<span class="text-[10px] sm:text-sm font-bold"> kg</span></p>
                        <p class="text-[10px] sm:text-xs text-emerald-100">Limbah dicegah</p>
                    </div>
                    <div>
                        <p class="text-base sm:text-2xl font-black tabular-nums">{{ $totalBookings ?? 0 }}</p>
                        <p class="text-[10px] sm:text-xs text-emerald-100">Transaksi COD</p>
                    </div>
                </div>
            </section>
        @endif

        <!-- Notifikasi -->
        @if(session('success'))
            <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">
                ✅ {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" role="alert">
                ⚠️ {{ session('error') }}
            </div>
        @endif

        <!-- Judul daftar -->
        <div class="flex items-baseline justify-between mb-3">
            <h2 class="text-base sm:text-lg font-bold text-slate-900">
                @if(request('search'))
                    Hasil pencarian "{{ request('search') }}"
                @elseif($activeKategori !== 'all')
                    {{ $categories[$activeKategori]['label'] ?? $activeKategori }}
                @else
                    Barang terbaru di Batam
                @endif
            </h2>
            <span class="text-xs text-slate-400">{{ $products->count() }} barang</span>
        </div>

        <!-- Grid produk -->
        <section id="katalog" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5 gap-3 sm:gap-4 mb-10">
            @forelse($products as $p)
                @php
                    $name = $p->title;
                    $img = filter_var($p->image_url ?? $p->image_path, FILTER_VALIDATE_URL)
                        ? ($p->image_url ?? $p->image_path)
                        : asset('storage/' . ($p->image_url ?? $p->image_path));
                    // "Grade A (Like New)" -> "Like New"
                    $kondisi = preg_match('/\(([^)]+)\)/', (string) $p->grade, $m) ? $m[1] : ($p->grade ?: 'Preloved');
                    $gradeLetter = preg_match('/Grade\s+([ABC])/i', (string) $p->grade, $g) ? strtoupper($g[1]) : null;
                    $kondisiClass = match ($gradeLetter) {
                        'A' => 'bg-emerald-600 text-white',
                        'B' => 'bg-amber-400 text-amber-950',
                        'C' => 'bg-slate-700 text-white',
                        default => 'bg-white/90 text-slate-700',
                    };
                    $isAvailable = strtolower($p->status) === 'available';
                    $isOwn = auth()->id() === $p->user_id;
                @endphp

                <article class="group bg-white rounded-xl border border-slate-200 overflow-hidden flex flex-col hover:shadow-lg hover:border-slate-300 transition">
                    <div class="relative aspect-square bg-slate-100 overflow-hidden">
                        <img src="{{ $img }}" alt="{{ $name }}" loading="lazy"
                             class="w-full h-full object-cover group-hover:scale-105 transition duration-300 {{ $isAvailable ? '' : 'opacity-60' }}"
                             onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1523381210434-271e8be1f52b?auto=format&fit=crop&w=500&q=80'">

                        <span class="absolute top-2 left-2 text-[10px] sm:text-[11px] font-bold px-2 py-0.5 rounded-md shadow-sm {{ $kondisiClass }}">{{ $kondisi }}</span>

                        @if($p->mode_jual === 'borongan')
                            <span class="absolute top-2 right-2 text-[10px] sm:text-[11px] font-bold px-2 py-0.5 rounded-md bg-white/90 text-slate-700 shadow-sm">📦 Borongan</span>
                        @endif

                        @unless($isAvailable)
                            <span class="absolute inset-x-0 bottom-0 bg-slate-900/75 text-white text-xs font-semibold text-center py-1.5">Sedang di-booking</span>
                        @endunless
                    </div>

                    <div class="p-2.5 sm:p-3 flex flex-col flex-1">
                        <h3 class="text-sm text-slate-800 leading-snug line-clamp-2 min-h-[2.5rem]" title="{{ $name }}">{{ $name }}</h3>

                        <p class="mt-1 text-base sm:text-lg font-bold text-emerald-700 tabular-nums">Rp{{ number_format($p->price, 0, ',', '.') }}</p>

                        @if($isAvailable && !$isOwn)
                            <p class="mt-0.5 text-[11px] font-semibold text-emerald-600 flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500" aria-hidden="true"></span> Bisa nego via chat
                            </p>
                        @endif

                        <p class="mt-1 text-[11px] text-slate-500 truncate">📍 {{ $p->user->lokasi_lapak ?? 'Batam' }} · {{ $p->user->nama_toko ?? $p->user->name ?? 'Penjual' }}</p>

                        <div class="mt-auto pt-2.5 flex gap-1.5">
                            @if($isOwn)
                                <span class="w-full text-center text-xs font-semibold text-slate-500 bg-slate-100 rounded-lg py-2">Barang kamu</span>
                            @else
                                <a href="{{ route('chat.index', ['user_id' => $p->user_id, 'product_id' => $p->id]) }}"
                                   class="shrink-0 w-9 h-9 flex items-center justify-center rounded-lg border border-emerald-600 text-emerald-700 hover:bg-emerald-50"
                                   aria-label="Chat penjual tentang {{ $name }}" title="Chat Penjual">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12Z"/></svg>
                                </a>
                                @if($isAvailable)
                                    <button type="button" data-url="{{ route('product.book', $p->id) }}" data-name="{{ $name }}" onclick="openBookingModal(this)"
                                            class="flex-1 h-9 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs sm:text-sm font-semibold">
                                        Booking COD
                                    </button>
                                @else
                                    <button type="button" disabled class="flex-1 h-9 rounded-lg bg-slate-100 text-slate-400 text-xs sm:text-sm font-semibold cursor-not-allowed">
                                        Di-booking
                                    </button>
                                @endif
                            @endif
                        </div>
                    </div>
                </article>
            @empty
                <div class="col-span-full text-center py-16 rounded-xl border border-dashed border-slate-300">
                    <span class="text-5xl block mb-2" aria-hidden="true">🛍️</span>
                    <p class="font-bold text-slate-800">
                        {{ request('search') || $activeKategori !== 'all' ? 'Barang tidak ditemukan' : 'Katalog masih kosong' }}
                    </p>
                    <p class="text-sm text-slate-500 mt-1">
                        @if(request('search') || $activeKategori !== 'all')
                            Coba kata kunci lain atau <a href="{{ route('home') }}" class="text-emerald-700 font-semibold underline">lihat semua barang</a>.
                        @else
                            Belum ada barang preloved yang ditayangkan saat ini.
                        @endif
                    </p>
                </div>
            @endforelse
        </section>
    </main>


    <!-- Modal Booking COD -->
    <div id="bookingModal" class="fixed inset-0 bg-slate-900/60 hidden items-end sm:items-center justify-center z-50 sm:p-4" role="dialog" aria-modal="true" aria-labelledby="bookingTitle">
        <div class="bg-white w-full sm:max-w-md rounded-t-2xl sm:rounded-2xl p-5 sm:p-6 shadow-xl">
            <div class="flex justify-between items-center mb-4">
                <h3 id="bookingTitle" class="text-base font-bold text-slate-900">Atur Janji COD</h3>
                <button type="button" onclick="closeBookingModal()" class="w-8 h-8 rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-600 text-xl" aria-label="Tutup">&times;</button>
            </div>

            <form id="bookingForm" method="POST" action="" class="space-y-3">
                @csrf
                <div>
                    <label for="modalProductName" class="block text-sm font-semibold text-slate-700 mb-1">Barang</label>
                    <input type="text" id="modalProductName" disabled class="w-full bg-slate-100 border-slate-200 rounded-lg text-sm font-semibold text-emerald-800">
                </div>
                <div>
                    <label for="lokasi_cod" class="block text-sm font-semibold text-slate-700 mb-1">Lokasi pertemuan COD</label>
                    <input type="text" id="lokasi_cod" name="lokasi_cod" placeholder="Contoh: Nagoya Hill / Mega Mall" required class="w-full border-slate-300 rounded-lg text-sm focus:border-emerald-500 focus:ring-emerald-500">
                </div>
                <div>
                    <label for="waktu_cod" class="block text-sm font-semibold text-slate-700 mb-1">Jadwal COD</label>
                    <input type="datetime-local" id="waktu_cod" name="waktu_cod" required class="w-full border-slate-300 rounded-lg text-sm focus:border-emerald-500 focus:ring-emerald-500">
                </div>
                <p class="text-xs text-slate-500">Barang akan dikunci untukmu dan kamu mendapat token QR untuk ditunjukkan ke penjual saat COD.</p>
                <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2.5 rounded-lg text-sm">
                    Booking & Dapatkan Token QR
                </button>
            </form>
        </div>
    </div>

    <script>
        function openBookingModal(button) {
            document.getElementById('modalProductName').value = button.dataset.name;
            document.getElementById('bookingForm').action = button.dataset.url;
            const modal = document.getElementById('bookingModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeBookingModal() {
            const modal = document.getElementById('bookingModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        document.getElementById('bookingModal').addEventListener('click', function (e) {
            if (e.target === this) closeBookingModal();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeBookingModal();
        });
    </script>
</x-market-layout>

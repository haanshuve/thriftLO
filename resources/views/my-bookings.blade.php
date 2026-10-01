<x-market-layout title="Tiket Saya">

    @php
        $activeStatuses = ['Pending', 'Confirmed'];
        $activeBookings = $bookings->whereIn('status_cod', $activeStatuses);
        $historyBookings = $bookings->whereNotIn('status_cod', $activeStatuses);
        $tab = request('tab', $activeBookings->isNotEmpty() || $historyBookings->isEmpty() ? 'aktif' : 'riwayat');
        $tab = in_array($tab, ['aktif', 'riwayat'], true) ? $tab : 'aktif';
        $shown = $tab === 'aktif' ? $activeBookings : $historyBookings;

        $statusClass = [
            'Pending'   => 'bg-amber-100 text-amber-800',
            'Confirmed' => 'bg-sky-100 text-sky-800',
            'Completed' => 'bg-emerald-100 text-emerald-800',
            'Cancelled' => 'bg-slate-200 text-slate-600',
        ];
        $statusLabel = [
            'Pending'   => 'Menunggu COD',
            'Confirmed' => 'Dikonfirmasi penjual',
            'Completed' => 'Selesai',
            'Cancelled' => 'Dibatalkan',
        ];
        $imgSrc = fn ($p) => filter_var($p->image_url ?? $p->image_path, FILTER_VALIDATE_URL)
            ? ($p->image_url ?? $p->image_path)
            : asset('storage/' . ($p->image_url ?? $p->image_path));
        $firstActiveId = $activeBookings->first()?->id;
    @endphp

    <main class="max-w-3xl mx-auto px-4 sm:px-6 py-4 sm:py-6 space-y-4">

        <h1 class="text-lg sm:text-xl font-bold text-slate-900">Tiket Saya</h1>

        @if(session('success'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">✅ {{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" role="alert">⚠️ {{ session('error') }}</div>
        @endif

        <!-- Tab status -->
        <nav class="flex border-b border-slate-200" aria-label="Status tiket">
            @foreach(['aktif' => ['Aktif', $activeBookings->count()], 'riwayat' => ['Riwayat', $historyBookings->count()]] as $key => [$label, $count])
                <a href="{{ route('bookings.index', ['tab' => $key]) }}"
                   class="flex-1 sm:flex-none sm:px-6 text-center py-2.5 text-sm font-semibold border-b-2 -mb-px transition {{ $tab === $key ? 'border-emerald-600 text-emerald-700' : 'border-transparent text-slate-500 hover:text-slate-700' }}"
                   @if($tab === $key) aria-current="page" @endif>
                    {{ $label }} <span class="ml-1 text-xs font-bold px-1.5 py-0.5 rounded-full {{ $tab === $key ? 'bg-emerald-100' : 'bg-slate-100' }}">{{ $count }}</span>
                </a>
            @endforeach
        </nav>

        <!-- Daftar tiket -->
        <div class="space-y-3">
            @forelse($shown as $b)
                @php
                    $p = $b->product;
                    $seller = $p->user;
                    $isActive = in_array($b->status_cod, $activeStatuses, true);
                    $reviewErrors = old('booking_id') == $b->id ? $errors->review : null;
                @endphp

                <article class="rounded-xl border border-slate-200 bg-white overflow-hidden">
                    <!-- Penjual & status -->
                    <div class="flex items-center justify-between gap-2 px-4 py-2.5 border-b border-slate-100 bg-slate-50/60">
                        <p class="text-sm font-semibold text-slate-700 truncate">🏬 {{ $seller->nama_toko ?? $seller->name ?? 'Penjual' }}</p>
                        <span class="shrink-0 text-[11px] font-semibold px-2 py-0.5 rounded-full {{ $statusClass[$b->status_cod] ?? 'bg-slate-100 text-slate-600' }}">
                            {{ $statusLabel[$b->status_cod] ?? $b->status_cod }}
                        </span>
                    </div>

                    <!-- Barang & info COD -->
                    <div class="p-4 flex gap-3">
                        <img src="{{ $imgSrc($p) }}" alt="" class="w-16 h-16 sm:w-20 sm:h-20 rounded-lg object-cover bg-slate-100 shrink-0"
                             onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1523381210434-271e8be1f52b?auto=format&fit=crop&w=200&q=70'">
                        <div class="flex-1 min-w-0">
                            <h2 class="text-sm sm:text-base text-slate-900 leading-snug line-clamp-2">{{ $p->title }}</h2>
                            <p class="mt-0.5 font-bold text-emerald-700 tabular-nums">Rp{{ number_format($p->price, 0, ',', '.') }}</p>
                            <p class="mt-1 text-xs text-slate-500">📍 {{ $b->cod_location }}</p>
                            <p class="text-xs text-slate-500">⏰ {{ \Illuminate\Support\Carbon::parse($b->cod_schedule)->format('d M Y, H:i') }}</p>
                        </div>
                    </div>

                    @if($isActive)
                        <!-- QR untuk COD -->
                        <details class="group border-t border-slate-100" @if($b->id === $firstActiveId) open @endif>
                            <summary class="list-none cursor-pointer select-none px-4 py-3 flex items-center justify-between text-sm font-semibold text-emerald-700 hover:bg-emerald-50/60">
                                <span>Tampilkan QR untuk COD</span>
                                <svg class="w-4 h-4 transition group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                            </summary>
                            <div class="px-4 pb-4 flex flex-col items-center text-center">
                                <div class="w-48 h-48 p-2 bg-white border border-slate-200 rounded-xl [&>svg]:w-full [&>svg]:h-full" role="img" aria-label="QR code token {{ $b->qr_token }}">
                                    {!! $b->qrCodeSvg() !!}
                                </div>
                                <p class="mt-3 font-mono text-lg font-bold tracking-widest text-slate-900">{{ $b->qr_token }}</p>
                                <button type="button" data-token="{{ $b->qr_token }}" onclick="copyToken(this)"
                                        class="mt-1 text-xs font-semibold text-emerald-700 hover:underline">Salin token</button>
                                <p class="mt-2 text-xs text-slate-500 max-w-xs">Tunjukkan QR atau token ini ke penjual saat bertemu. Jangan bagikan sebelum barang kamu cek.</p>
                            </div>
                        </details>

                        <div class="px-4 pb-4 pt-0">
                            <a href="{{ route('chat.index', ['user_id' => $p->user_id, 'product_id' => $p->id]) }}"
                               class="flex items-center justify-center gap-2 h-10 rounded-lg border border-emerald-600 text-emerald-700 hover:bg-emerald-50 text-sm font-semibold">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12Z"/></svg>
                                Chat Penjual
                            </a>
                        </div>

                    @elseif($b->status_cod === 'Completed')
                        <!-- Ulasan -->
                        <div class="border-t border-slate-100 px-4 py-3">
                            @if($b->review)
                                <div class="flex items-center gap-2">
                                    <span class="text-amber-400 text-lg leading-none" aria-label="{{ $b->review->rating }} dari 5 bintang">
                                        {{ str_repeat('★', $b->review->rating) }}<span class="text-slate-300">{{ str_repeat('★', 5 - $b->review->rating) }}</span>
                                    </span>
                                    <span class="text-xs text-slate-400">Ulasanmu</span>
                                </div>
                                @if($b->review->comment)
                                    <p class="mt-1 text-sm text-slate-600">“{{ $b->review->comment }}”</p>
                                @endif
                            @else
                                <form action="{{ route('review.store', $b->id) }}" method="POST" class="space-y-2">
                                    @csrf
                                    <input type="hidden" name="booking_id" value="{{ $b->id }}">
                                    <fieldset>
                                        <legend class="text-sm font-semibold text-slate-800">Beri ulasan untuk penjual</legend>
                                        <div class="mt-1 flex flex-row-reverse justify-end gap-1">
                                            @for($i = 5; $i >= 1; $i--)
                                                <input type="radio" id="rating-{{ $b->id }}-{{ $i }}" name="rating" value="{{ $i }}" class="sr-only [&:checked~label]:text-amber-400 [&:focus-visible+label]:ring-2 [&:focus-visible+label]:ring-emerald-500"
                                                       @checked($reviewErrors !== null && (int) old('rating') === $i) @if($i === 5) required @endif>
                                                <label for="rating-{{ $b->id }}-{{ $i }}" class="text-3xl leading-none text-slate-300 cursor-pointer rounded hover:text-amber-400 [&:hover~label]:text-amber-400">
                                                    ★<span class="sr-only">{{ $i }} bintang</span>
                                                </label>
                                            @endfor
                                        </div>
                                    </fieldset>
                                    <label for="comment-{{ $b->id }}" class="sr-only">Komentar ulasan</label>
                                    <textarea id="comment-{{ $b->id }}" name="comment" rows="2" maxlength="500" placeholder="Bagaimana kondisi barang dan pengalaman COD-nya? (opsional)"
                                              class="w-full border-slate-300 rounded-lg text-sm focus:border-emerald-500 focus:ring-emerald-500">{{ $reviewErrors !== null ? old('comment') : '' }}</textarea>
                                    @if($reviewErrors?->any())
                                        <p class="text-sm text-rose-600" role="alert">{{ $reviewErrors->first() }}</p>
                                    @endif
                                    <button type="submit" class="w-full sm:w-auto px-5 h-10 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold">Kirim ulasan</button>
                                </form>
                            @endif
                        </div>
                    @endif
                </article>
            @empty
                <div class="text-center py-14 rounded-xl border border-dashed border-slate-300">
                    <p class="text-4xl mb-2" aria-hidden="true">🎟️</p>
                    @if($tab === 'aktif')
                        <p class="font-bold text-slate-800">Belum ada tiket aktif</p>
                        <p class="text-sm text-slate-500 mt-1">Booking barang di katalog untuk mendapatkan token QR COD.</p>
                    @else
                        <p class="font-bold text-slate-800">Belum ada riwayat transaksi</p>
                        <p class="text-sm text-slate-500 mt-1">Transaksi COD yang sudah selesai akan muncul di sini.</p>
                    @endif
                    <a href="{{ route('home') }}" class="inline-block mt-4 px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold">Cari barang preloved</a>
                </div>
            @endforelse
        </div>
    </main>

    <script>
        function copyToken(button) {
            const token = button.dataset.token;
            const done = () => {
                button.textContent = 'Tersalin ✓';
                setTimeout(() => { button.textContent = 'Salin token'; }, 2000);
            };
            if (navigator.clipboard) {
                navigator.clipboard.writeText(token).then(done).catch(() => {});
            }
        }
    </script>
</x-market-layout>

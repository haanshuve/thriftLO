<x-market-layout title="Tiket Saya">

    @php
        $activeStatuses = \App\Models\Booking::ACTIVE_STATUSES;
        $rupiah = fn ($n) => 'Rp' . number_format($n, 0, ',', '.');
        // Popup pembatalan dibuka lagi kalau validasinya gagal (mis. kotak persetujuan belum dicentang)
        $cancelErrorBookingId = $errors->cancel->any() ? (int) old('booking_id') : null;
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

        <!-- Pesanan lewat pengiriman -->
        @php $orders = $orders ?? collect(); @endphp
        @if($orders->isNotEmpty())
            <section aria-labelledby="judulPesanan" class="space-y-3">
                <h2 id="judulPesanan" class="text-base font-bold text-slate-900">Pesanan pengiriman</h2>
                @foreach($orders as $order)
                    @php $op = $order->product; $awaiting = $order->status === \App\Models\Order::AWAITING_SHIPMENT; @endphp
                    <article class="rounded-xl border border-slate-200 bg-white overflow-hidden">
                        <div class="flex items-center justify-between gap-2 px-4 py-2.5 border-b border-slate-100 bg-slate-50/60">
                            <p class="text-sm font-semibold text-slate-700 truncate">🏬 {{ $op->user->nama_toko ?? $op->user->name ?? 'Penjual' }}</p>
                            <span class="shrink-0 text-[11px] font-semibold px-2 py-0.5 rounded-full {{ $awaiting ? 'bg-amber-100 text-amber-800' : ($order->isReleased() ? 'bg-emerald-100 text-emerald-800' : 'bg-sky-100 text-sky-800') }}">{{ $order->statusLabel() }}</span>
                        </div>
                        <div class="p-4 flex gap-3">
                            <img src="{{ $imgSrc($op) }}" alt="" class="w-16 h-16 sm:w-20 sm:h-20 rounded-lg object-cover bg-slate-100 shrink-0"
                                 onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1523381210434-271e8be1f52b?auto=format&fit=crop&w=200&q=70'">
                            <div class="flex-1 min-w-0 text-xs text-slate-500 space-y-0.5">
                                <h3 class="text-sm sm:text-base text-slate-900 leading-snug line-clamp-2">{{ $op->title }}</h3>
                                <p>🚚 {{ $order->courier }} · Ongkir Rp{{ number_format($order->shipping_cost, 0, ',', '.') }}</p>
                                <p class="text-sm font-bold text-emerald-700 tabular-nums">Total Rp{{ number_format($order->total_price, 0, ',', '.') }}</p>
                                <p class="whitespace-pre-line">🏠 {{ $order->shipping_address }}</p>
                                <p>{{ $awaiting ? 'Dipesan ' . $order->created_at->format('d M Y, H:i') . ', menunggu penjual mengirim.' : 'Dikirim ' . $order->shipped_at?->format('d M Y, H:i') . '.' }}</p>
                            </div>
                        </div>
                        @if($order->isReleased())
                            <p class="mx-4 mb-3 rounded-lg bg-emerald-50 border border-emerald-200 px-3 py-2 text-xs text-emerald-800">
                                ✅ Pesanan selesai {{ $order->released_at->format('d M Y, H:i') }}. Dana sudah diteruskan ke penjual.
                            </p>
                        @elseif(!$awaiting)
                            <!-- Escrow: dana dipegang thriftLO sampai pembeli konfirmasi -->
                            <div class="mx-4 mb-3 rounded-lg bg-sky-50 border border-sky-200 px-3 py-2.5 text-xs text-sky-900 space-y-2">
                                <p>🔒 Pembayaranmu masih ditahan thriftLO. Sudah terima barangnya dan sesuai? Konfirmasi supaya dana diteruskan ke penjual. Kalau tidak dikonfirmasi, dana cair otomatis {{ $order->autoReleaseAt()->format('d M Y, H:i') }}.</p>
                                <form action="{{ route('order.confirm', $order) }}" method="POST"
                                      onsubmit="return confirm('Konfirmasi barang &quot;{{ addslashes($op->title) }}&quot; sudah diterima? Dana akan diteruskan ke penjual dan tidak bisa dibatalkan.')">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="w-full h-10 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold">Barang Sudah Diterima</button>
                                </form>
                            </div>
                        @endif
                        <div class="px-4 pb-4">
                            <a href="{{ route('chat.index', ['user_id' => $op->user_id, 'product_id' => $op->id]) }}"
                               class="flex items-center justify-center gap-2 h-10 rounded-lg border border-emerald-600 text-emerald-700 hover:bg-emerald-50 text-sm font-semibold">
                                Chat Penjual
                            </a>
                        </div>
                    </article>
                @endforeach
            </section>

            <h2 class="text-base font-bold text-slate-900 pt-2">Tiket COD</h2>
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
                            {{ $b->statusLabel() }}
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
                            @if($b->booking_fee > 0)
                                <p class="text-xs text-slate-500">💳 Biaya booking {{ $rupiah($b->booking_fee) }} · {{ $b->feeStatusLabel() }}</p>
                            @endif
                        </div>
                    </div>

                    @if($b->status_cod === 'Cancelled')
                        <!-- Informasi pembatalan -->
                        @php $bySeller = $b->cancelled_by === \App\Models\Booking::CANCELLED_BY_SELLER; @endphp
                        <div class="mx-4 mb-4 rounded-lg border px-3 py-2.5 text-xs space-y-0.5 {{ $bySeller ? 'border-sky-200 bg-sky-50 text-sky-900' : 'border-slate-200 bg-slate-50 text-slate-700' }}">
                            <p class="font-semibold">
                                {{ $bySeller ? 'Penjual membatalkan booking ini' : ($b->cancelled_by === \App\Models\Booking::CANCELLED_BY_SYSTEM ? 'Booking ini dibatalkan otomatis oleh sistem' : ($b->cancelled_by ? 'Kamu membatalkan booking ini' : 'Booking ini dibatalkan')) }}{{ $b->cancelled_at ? ' pada ' . $b->cancelled_at->format('d M Y, H:i') : '' }}.
                            </p>
                            @if($b->cancelReasonLabel())
                                <p>Alasan: {{ $b->cancelReasonLabel() }}</p>
                            @endif
                            {{-- Status refunded hanya ada di data lama --}}
                            @if($b->fee_status === \App\Models\Booking::FEE_REFUNDED)
                                <p>✅ Biaya booking {{ $rupiah($b->booking_fee) }} dikembalikan ke kamu.</p>
                            @elseif($b->fee_status === \App\Models\Booking::FEE_FORFEITED)
                                <p>Biaya booking {{ $rupiah($b->booking_fee) }} tidak dapat dikembalikan dan menjadi milik platform.</p>
                            @endif
                            <p>Token QR booking ini sudah tidak berlaku.</p>
                        </div>
                    @endif

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
                            @if($cancelErrorBookingId === $b->id)
                                <p class="mt-2 text-sm text-rose-600" role="alert">{{ $errors->cancel->first() }}</p>
                            @endif
                            <button type="button" onclick="openCancelModal(this)"
                                    data-booking-id="{{ $b->id }}"
                                    data-url="{{ route('booking.cancel', $b) }}"
                                    data-product="{{ $p->title }}"
                                    data-seller="{{ $seller->nama_toko ?? $seller->name ?? 'Penjual' }}"
                                    data-schedule="{{ \Illuminate\Support\Carbon::parse($b->cod_schedule)->format('d M Y, H:i') }} · {{ $b->cod_location }}"
                                    data-status="{{ $b->statusLabel() }}"
                                    data-fee="{{ $b->booking_fee > 0 ? $rupiah($b->booking_fee) : '' }}"
                                    class="mt-2 w-full h-10 rounded-lg text-sm font-semibold text-rose-600 hover:bg-rose-50">
                                Batalkan booking
                            </button>
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
                        <p class="text-sm text-slate-500 mt-1">Transaksi COD yang sudah selesai atau dibatalkan akan muncul di sini.</p>
                    @endif
                    <a href="{{ route('home') }}" class="inline-block mt-4 px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold">Cari barang preloved</a>
                </div>
            @endforelse
        </div>
    </main>

    <!-- Popup konsekuensi pembatalan oleh pembeli -->
    <div id="cancelModal" class="fixed inset-0 bg-slate-900/60 hidden items-end sm:items-center justify-center z-50 sm:p-4" role="dialog" aria-modal="true" aria-labelledby="cancelTitle">
        <div class="animate-fade-up bg-white w-full sm:max-w-md rounded-t-2xl sm:rounded-2xl p-5 sm:p-6 shadow-xl max-h-[92vh] overflow-y-auto">
            <div class="flex justify-between items-start gap-3 mb-3">
                <h3 id="cancelTitle" class="text-base font-bold text-slate-900">Batalkan booking COD?</h3>
                <button type="button" onclick="closeCancelModal()" class="w-8 h-8 shrink-0 rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-600 text-xl transition" aria-label="Tutup">&times;</button>
            </div>

            <form id="cancelForm" method="POST" action="" class="space-y-3">
                @csrf
                @method('PATCH')
                <input type="hidden" name="booking_id" id="cancelBookingId" value="">

                <dl class="rounded-xl border border-slate-200 divide-y divide-slate-100 text-sm">
                    <div class="flex justify-between gap-3 px-3 py-2"><dt class="text-slate-500">Barang</dt><dd id="cancelProduct" class="font-semibold text-slate-900 text-right"></dd></div>
                    <div class="flex justify-between gap-3 px-3 py-2"><dt class="text-slate-500">Penjual</dt><dd id="cancelSeller" class="text-slate-800 text-right"></dd></div>
                    <div class="flex justify-between gap-3 px-3 py-2"><dt class="text-slate-500">Jadwal</dt><dd id="cancelSchedule" class="text-slate-800 text-right"></dd></div>
                    <div class="flex justify-between gap-3 px-3 py-2"><dt class="text-slate-500">Status</dt><dd id="cancelStatus" class="text-slate-800 text-right"></dd></div>
                </dl>

                <div id="cancelFeeWarning" class="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2.5 text-sm text-amber-900" role="note">
                    <p class="font-bold">⚠️ Biaya booking <span id="cancelFee"></span> yang sudah dibayar tidak dapat dikembalikan dan menjadi milik platform.</p>
                    <p class="mt-1 text-xs text-amber-800">Biaya booking menjaga barang tetap tertahan untukmu dan melindungi penjual dari pembatalan sepihak. Setelah dibatalkan, barang tayang lagi di katalog dan token QR-mu tidak berlaku.</p>
                </div>

                <div>
                    <label for="cancelReason" class="block text-sm font-semibold text-slate-700 mb-1">Alasan pembatalan <span class="font-normal text-slate-400">(opsional)</span></label>
                    <select id="cancelReason" name="alasan" class="w-full border-slate-300 rounded-lg text-sm focus:border-emerald-500 focus:ring-emerald-500">
                        <option value="">Pilih alasan</option>
                        @foreach(\App\Models\Booking::BUYER_CANCEL_REASONS as $value => $label)
                            <option value="{{ $value }}" @selected(old('alasan') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <label class="flex items-start gap-2.5 rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-700 cursor-pointer has-[:checked]:border-rose-300 has-[:checked]:bg-rose-50/50">
                    <input type="checkbox" id="cancelAgree" name="setuju_konsekuensi" value="1" class="mt-0.5 rounded border-slate-300 text-rose-600 focus:ring-rose-500">
                    <span id="cancelAgreeText">Saya mengerti dan setuju biaya booking tidak dapat dikembalikan dan menjadi milik platform</span>
                </label>

                <p id="cancelError" class="hidden text-sm text-rose-600" role="alert"></p>

                <div class="flex flex-col-reverse sm:flex-row gap-2 pt-1">
                    <button type="submit" id="cancelSubmit" disabled
                            class="sm:flex-1 h-10 rounded-lg border border-rose-300 text-rose-700 hover:bg-rose-50 text-sm font-semibold disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-transparent">
                        Ya, batalkan booking
                    </button>
                    <button type="button" onclick="closeCancelModal()" class="sm:flex-1 h-10 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold">
                        Tetap lanjutkan booking
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openCancelModal(button) {
            const d = button.dataset;
            document.getElementById('cancelForm').action = d.url;
            document.getElementById('cancelBookingId').value = d.bookingId;
            document.getElementById('cancelProduct').textContent = d.product;
            document.getElementById('cancelSeller').textContent = d.seller;
            document.getElementById('cancelSchedule').textContent = d.schedule;
            document.getElementById('cancelStatus').textContent = d.status;

            // Booking lama tanpa biaya booking: tidak ada biaya yang menjadi milik platform
            const hasFee = d.fee !== '';
            document.getElementById('cancelFee').textContent = d.fee;
            document.getElementById('cancelFeeWarning').classList.toggle('hidden', !hasFee);
            document.getElementById('cancelAgreeText').textContent = hasFee
                ? 'Saya mengerti dan setuju biaya booking tidak dapat dikembalikan dan menjadi milik platform'
                : 'Saya mengerti barang akan tayang lagi dan token QR-ku tidak berlaku';

            const agree = document.getElementById('cancelAgree');
            agree.checked = false;
            document.getElementById('cancelSubmit').disabled = true;

            const error = document.getElementById('cancelError');
            error.textContent = d.error || '';
            error.classList.toggle('hidden', !d.error);

            const modal = document.getElementById('cancelModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            agree.focus();
        }

        function closeCancelModal() {
            const modal = document.getElementById('cancelModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        // Tombol pembatalan baru aktif setelah kotak persetujuan dicentang (server tetap memvalidasi)
        document.getElementById('cancelAgree').addEventListener('change', function () {
            document.getElementById('cancelSubmit').disabled = !this.checked;
        });
        document.getElementById('cancelModal').addEventListener('click', function (e) {
            if (e.target === this) closeCancelModal();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeCancelModal();
        });

        @if($cancelErrorBookingId)
            // Validasi pembatalan gagal: buka lagi popup untuk booking yang sama beserta pesannya
            (function () {
                const button = document.querySelector('[data-booking-id="{{ $cancelErrorBookingId }}"]');
                if (button) {
                    button.dataset.error = @json($errors->cancel->first());
                    openCancelModal(button);
                }
            })();
        @endif

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

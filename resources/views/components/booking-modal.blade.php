{{--
    Modal booking COD, tiga tahap: jadwal → ringkasan → bayar biaya booking via QRIS.
    Buka dengan tombol ber-atribut data-url, data-name, data-price dan onclick="openBookingModal(this)".
    Booking baru dibuat setelah pembeli menekan "Saya sudah bayar", jadi batal sebelum itu tidak dikenai apa pun.
--}}
@php
    $bookingFee = (int) config('thriftlo.booking.fee');
    $rupiahFee = 'Rp' . number_format($bookingFee, 0, ',', '.');
    // Pembayaran masih SIMULASI: QR ini contoh, bukan kode QRIS merchant sungguhan
    $qrisSvg = \App\Support\QrCode::svg('THRIFTLO-QRIS-SIMULASI;BIAYA-BOOKING=' . $bookingFee, 180);
    $input = 'w-full border-slate-300 rounded-lg text-sm focus:border-emerald-500 focus:ring-emerald-500';
@endphp

<div id="bookingModal" class="fixed inset-0 bg-slate-900/60 hidden items-end sm:items-center justify-center z-50 sm:p-4" role="dialog" aria-modal="true" aria-labelledby="bookingTitle">
    <div class="animate-fade-up bg-white w-full sm:max-w-md rounded-t-2xl sm:rounded-2xl p-5 sm:p-6 shadow-xl max-h-[92vh] overflow-y-auto">
        <div class="flex justify-between items-start gap-3 mb-3">
            <div>
                <h3 id="bookingTitle" class="text-base font-bold text-slate-900">Booking COD</h3>
                <p id="bookingSubtitle" class="text-sm text-slate-500 mt-0.5">Atur tempat dan waktu ketemuan sama penjualnya di Batam.</p>
            </div>
            <button type="button" onclick="closeBookingModal()" class="w-8 h-8 shrink-0 rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-600 text-xl transition" aria-label="Tutup">&times;</button>
        </div>

        <!-- Penanda tahap -->
        <ol class="mb-4 grid grid-cols-3 gap-1.5 text-[11px] font-semibold text-center" aria-label="Tahap booking">
            @foreach(['Jadwal', 'Ringkasan', 'Bayar QRIS'] as $i => $label)
                <li data-step-indicator="{{ $i + 1 }}" class="rounded-full py-1 bg-slate-100 text-slate-500">{{ $i + 1 }}. {{ $label }}</li>
            @endforeach
        </ol>

        <form id="bookingForm" method="POST" action="">
            @csrf
            <input type="hidden" name="qris_paid" value="1">

            <!-- Tahap 1: jadwal -->
            <div data-step="1" class="space-y-3">
                <div>
                    <label for="modalProductName" class="block text-sm font-semibold text-slate-700 mb-1">Barang</label>
                    <input type="text" id="modalProductName" disabled class="w-full bg-slate-100 border-slate-200 rounded-lg text-sm font-semibold text-emerald-800">
                </div>
                <div>
                    <label for="lokasi_cod" class="block text-sm font-semibold text-slate-700 mb-1">Mau ketemuan di mana?</label>
                    <input type="text" id="lokasi_cod" name="lokasi_cod" maxlength="255" placeholder="Contoh: Mega Mall Batam Centre" required class="{{ $input }}">
                </div>
                <div>
                    <label for="waktu_cod" class="block text-sm font-semibold text-slate-700 mb-1">Kapan?</label>
                    <input type="datetime-local" id="waktu_cod" name="waktu_cod" required class="{{ $input }}">
                </div>
                <button type="button" onclick="goToBookingStep(2)" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2.5 rounded-lg text-sm transition active:scale-[0.98] motion-reduce:transform-none">
                    Lanjut ke ringkasan
                </button>
            </div>

            <!-- Tahap 2: ringkasan sebelum membayar -->
            <div data-step="2" class="hidden space-y-3">
                <dl class="rounded-xl border border-slate-200 divide-y divide-slate-100 text-sm">
                    <div class="flex justify-between gap-3 px-3 py-2"><dt class="text-slate-500">Barang</dt><dd id="summaryProduct" class="font-semibold text-slate-900 text-right"></dd></div>
                    <div class="flex justify-between gap-3 px-3 py-2"><dt class="text-slate-500">Lokasi COD</dt><dd id="summaryLocation" class="text-slate-800 text-right"></dd></div>
                    <div class="flex justify-between gap-3 px-3 py-2"><dt class="text-slate-500">Jadwal</dt><dd id="summarySchedule" class="text-slate-800 text-right"></dd></div>
                    <div class="flex justify-between gap-3 px-3 py-2"><dt class="text-slate-500">Harga barang <span class="block text-[11px]">dibayar saat COD</span></dt><dd id="summaryPrice" class="text-slate-800 tabular-nums text-right"></dd></div>
                    <div class="flex justify-between gap-3 px-3 py-2 bg-emerald-50/60"><dt class="font-semibold text-slate-800">Bayar sekarang <span class="block text-[11px] font-normal text-slate-500">biaya booking via QRIS</span></dt><dd class="font-bold text-emerald-700 tabular-nums">{{ $rupiahFee }}</dd></div>
                </dl>

                <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 text-xs text-amber-900" role="note">
                    <p class="font-semibold">⚠️ Biaya booking tidak dapat dikembalikan bila kamu membatalkan.</p>
                    <p class="mt-0.5">Biaya ini menahan barang khusus untukmu sampai jadwal COD. Kalau penjual yang membatalkan atau tidak hadir, biaya booking dikembalikan.</p>
                </div>

                <p class="text-xs text-slate-500">Belum ada biaya yang ditarik. Kamu masih bisa membatalkan di tahap ini tanpa dikenai apa pun.</p>

                <div class="flex gap-2">
                    <button type="button" onclick="goToBookingStep(1)" class="flex-1 h-10 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 text-sm font-semibold">Kembali</button>
                    <button type="button" onclick="goToBookingStep(3)" class="flex-[2] h-10 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold">Bayar {{ $rupiahFee }} via QRIS</button>
                </div>
            </div>

            <!-- Tahap 3: bayar QRIS (simulasi) -->
            <div data-step="3" class="hidden space-y-3 text-center">
                <p class="text-sm text-slate-600">Pindai QRIS ini dengan e-wallet atau mobile banking.</p>
                <div class="mx-auto w-44 h-44 p-2 bg-white border border-slate-200 rounded-xl [&>svg]:w-full [&>svg]:h-full" role="img" aria-label="Kode QRIS biaya booking">
                    {!! $qrisSvg !!}
                </div>
                <p class="text-2xl font-black text-slate-900 tabular-nums">{{ $rupiahFee }}</p>
                <p class="text-xs text-slate-500">Biaya booking COD · thriftLO</p>
                <p class="rounded-lg bg-slate-50 border border-slate-200 px-3 py-2 text-[11px] text-slate-500">Mode demo: pembayaran disimulasikan, tidak ada uang yang benar-benar ditarik.</p>

                <button type="submit" id="bookingSubmit" class="w-full inline-flex items-center justify-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2.5 rounded-lg text-sm transition active:scale-[0.98] disabled:opacity-70 disabled:cursor-wait motion-reduce:transform-none">
                    Saya sudah bayar
                </button>
                <button type="button" onclick="closeBookingModal()" class="w-full h-10 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 text-sm font-semibold">
                    Batalkan pembayaran
                </button>
                <p class="text-xs text-slate-500">Membatalkan sebelum membayar tidak dikenai biaya apa pun.</p>
            </div>
        </form>
    </div>
</div>

<script>
    const bookingSubtitles = {
        1: 'Atur tempat dan waktu ketemuan sama penjualnya di Batam.',
        2: 'Cek lagi sebelum membayar biaya booking.',
        3: 'Bayar biaya booking supaya barangnya dikunci buat kamu.',
    };

    function goToBookingStep(step) {
        // Jadwal wajib valid sebelum lanjut ke ringkasan
        if (step > 1) {
            for (const id of ['lokasi_cod', 'waktu_cod']) {
                const field = document.getElementById(id);
                if (!field.reportValidity()) return;
            }
            const rupiah = n => 'Rp' + Number(n).toLocaleString('id-ID');
            const when = new Date(document.getElementById('waktu_cod').value);
            document.getElementById('summaryProduct').textContent = document.getElementById('modalProductName').value;
            document.getElementById('summaryLocation').textContent = document.getElementById('lokasi_cod').value;
            document.getElementById('summarySchedule').textContent = when.toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
            document.getElementById('summaryPrice').textContent = rupiah(document.getElementById('bookingForm').dataset.price || 0);
        }

        document.querySelectorAll('#bookingForm [data-step]').forEach(el => el.classList.toggle('hidden', Number(el.dataset.step) !== step));
        document.querySelectorAll('[data-step-indicator]').forEach(el => {
            const n = Number(el.dataset.stepIndicator);
            el.className = 'rounded-full py-1 ' + (n === step ? 'bg-emerald-600 text-white' : n < step ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500');
        });
        document.getElementById('bookingSubtitle').textContent = bookingSubtitles[step];
    }

    function openBookingModal(button) {
        const form = document.getElementById('bookingForm');
        form.reset();
        form.action = button.dataset.url;
        form.dataset.price = button.dataset.price || 0;
        document.getElementById('modalProductName').value = button.dataset.name;

        // Jadwal COD tidak bisa dipilih di masa lalu
        const now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        document.getElementById('waktu_cod').min = now.toISOString().slice(0, 16);

        goToBookingStep(1);
        const modal = document.getElementById('bookingModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.getElementById('lokasi_cod').focus();
    }

    function closeBookingModal() {
        const modal = document.getElementById('bookingModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    // Loading state: kunci tombol supaya booking tidak terkirim dua kali
    document.getElementById('bookingForm').addEventListener('submit', function () {
        const button = document.getElementById('bookingSubmit');
        button.disabled = true;
        button.innerHTML = '<svg class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".3" stroke-width="3"/><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg> Memproses pembayaran...';
    });

    document.getElementById('bookingModal').addEventListener('click', function (e) {
        if (e.target === this) closeBookingModal();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeBookingModal();
    });
</script>

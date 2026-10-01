{{-- Modal booking COD. Buka dengan tombol ber-atribut data-url & data-name dan onclick="openBookingModal(this)" --}}
<div id="bookingModal" class="fixed inset-0 bg-slate-900/60 hidden items-end sm:items-center justify-center z-50 sm:p-4" role="dialog" aria-modal="true" aria-labelledby="bookingTitle">
    <div class="animate-fade-up bg-white w-full sm:max-w-md rounded-t-2xl sm:rounded-2xl p-5 sm:p-6 shadow-xl">
        <div class="flex justify-between items-start gap-3 mb-4">
            <div>
                <h3 id="bookingTitle" class="text-base font-bold text-slate-900">Booking COD</h3>
                <p class="text-sm text-slate-500 mt-0.5">Atur tempat dan waktu ketemuan sama penjualnya di Batam.</p>
            </div>
            <button type="button" onclick="closeBookingModal()" class="w-8 h-8 shrink-0 rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-600 text-xl transition" aria-label="Tutup">&times;</button>
        </div>

        <form id="bookingForm" method="POST" action="" class="space-y-3">
            @csrf
            <div>
                <label for="modalProductName" class="block text-sm font-semibold text-slate-700 mb-1">Barang</label>
                <input type="text" id="modalProductName" disabled class="w-full bg-slate-100 border-slate-200 rounded-lg text-sm font-semibold text-emerald-800">
            </div>
            <div>
                <label for="lokasi_cod" class="block text-sm font-semibold text-slate-700 mb-1">Mau ketemuan di mana?</label>
                <input type="text" id="lokasi_cod" name="lokasi_cod" placeholder="Contoh: Mega Mall Batam Centre" required class="w-full border-slate-300 rounded-lg text-sm focus:border-emerald-500 focus:ring-emerald-500">
            </div>
            <div>
                <label for="waktu_cod" class="block text-sm font-semibold text-slate-700 mb-1">Kapan?</label>
                <input type="datetime-local" id="waktu_cod" name="waktu_cod" required class="w-full border-slate-300 rounded-lg text-sm focus:border-emerald-500 focus:ring-emerald-500">
            </div>
            <p class="text-xs text-slate-500">Barangnya langsung dikunci buat kamu. Kamu dapat token QR yang tinggal ditunjukin ke penjual pas ketemuan, dan baru bayar setelah barangnya kamu cek.</p>
            <button type="submit" id="bookingSubmit" class="w-full inline-flex items-center justify-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2.5 rounded-lg text-sm transition active:scale-[0.98] disabled:opacity-70 disabled:cursor-wait motion-reduce:transform-none">
                Kunci Barangnya
            </button>
        </form>
    </div>
</div>

<script>
    function openBookingModal(button) {
        document.getElementById('modalProductName').value = button.dataset.name;
        document.getElementById('bookingForm').action = button.dataset.url;

        // Jadwal COD tidak bisa dipilih di masa lalu
        const now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        document.getElementById('waktu_cod').min = now.toISOString().slice(0, 16);

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
        button.innerHTML = '<svg class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".3" stroke-width="3"/><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg> Lagi ngunciin barangnya...';
    });

    document.getElementById('bookingModal').addEventListener('click', function (e) {
        if (e.target === this) closeBookingModal();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeBookingModal();
    });
</script>

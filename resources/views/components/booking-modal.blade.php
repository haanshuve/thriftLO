{{--
    Modal booking COD, tiga tahap: jadwal & titik ketemuan → ringkasan → bayar biaya booking via QRIS.
    Buka dengan tombol ber-atribut data-url, data-name, data-price dan onclick="openBookingModal(this)".
    Kalau penjual punya lokasi acuan COD, tambahkan data-seller-lat, data-seller-lng dan data-seller-label.
    Pembeli memasang pin titik ketemuan; server memeriksa ulang wilayah Batam dan jarak maksimal dari
    lokasi acuan penjual (config thriftlo.cod_point), jadi angka di sini hanya bantuan.
    Simulasi: booking baru dibuat setelah pembeli menekan "Saya sudah bayar", jadi batal sebelum itu tidak dikenai apa pun.
    Midtrans: tombol bayar di ringkasan langsung membuat tagihan QRIS dan membuka halaman bayar (booking.pay).
--}}
@php
    $bookingFee = (int) config('thriftlo.booking.fee');
    $rupiahFee = 'Rp' . number_format($bookingFee, 0, ',', '.');
    $simulatedPayment = app(\App\Payments\BookingFeeGateway::class)->isSimulation();
    // Mode simulasi: QR ini contoh, bukan kode QRIS merchant sungguhan
    $qrisSvg = $simulatedPayment ? \App\Support\QrCode::svg('THRIFTLO-QRIS-SIMULASI;BIAYA-BOOKING=' . $bookingFee, 180) : null;
    $input = 'w-full border-slate-300 rounded-lg text-sm focus:border-emerald-500 focus:ring-emerald-500';
    $codPoint = config('thriftlo.cod_point');
    $noteMax = (int) $codPoint['meeting_note_max'];
    $bookingErrors = $errors->booking;
@endphp

@if($bookingErrors->any())
    <!-- Booking ditolak server (mis. titik di luar Batam atau terlalu jauh): popup sudah tertutup setelah redirect -->
    <div id="bookingErrorBanner" class="fixed top-20 inset-x-4 sm:inset-x-auto sm:right-6 sm:w-96 z-50 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 shadow-lg" role="alert">
        <div class="flex items-start gap-3">
            <div class="flex-1">
                <p class="font-semibold">Booking COD belum dibuat:</p>
                <ul class="list-disc list-inside mt-0.5">
                    @foreach($bookingErrors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            <button type="button" onclick="this.closest('[role=alert]').remove()" class="w-7 h-7 shrink-0 rounded-lg text-rose-400 hover:bg-rose-100 text-lg" aria-label="Tutup pesan">&times;</button>
        </div>
    </div>
@endif

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
      integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">

<div id="bookingModal" class="fixed inset-0 bg-slate-900/60 hidden items-end sm:items-center justify-center z-50 sm:p-4" role="dialog" aria-modal="true" aria-labelledby="bookingTitle">
    <div class="animate-fade-up bg-white w-full sm:max-w-md rounded-t-2xl sm:rounded-2xl p-5 sm:p-6 shadow-xl max-h-[92vh] overflow-y-auto">
        <div class="flex justify-between items-start gap-3 mb-3">
            <div>
                <h3 id="bookingTitle" class="text-base font-bold text-slate-900">Booking COD</h3>
                <p id="bookingSubtitle" class="text-sm text-slate-500 mt-0.5">Pilih titik ketemuan yang adil buat kamu dan penjual.</p>
            </div>
            <button type="button" onclick="closeBookingModal()" class="w-8 h-8 shrink-0 rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-600 text-xl transition" aria-label="Tutup">&times;</button>
        </div>

        <!-- Penanda tahap -->
        <ol class="mb-4 grid grid-cols-3 gap-1.5 text-[11px] font-semibold text-center" aria-label="Tahap booking">
            @foreach(['Jadwal', 'Ringkasan', 'Bayar QRIS'] as $i => $label)
                <li data-step-indicator="{{ $i + 1 }}" class="rounded-full py-1 bg-slate-100 text-slate-500">{{ $i + 1 }}. {{ $label }}</li>
            @endforeach
        </ol>

        <form id="bookingForm" method="POST" action=""
              data-max-each-km="{{ $codPoint['max_each_km'] }}" data-snap-km="{{ $codPoint['midpoint_snap_km'] }}" data-fair-km="{{ $codPoint['fairness_warning_km'] }}"
              data-spots="{{ json_encode(config('thriftlo.cod_spots')) }}"
              data-areas="{{ json_encode($codPoint['areas']) }}"
              data-center-lat="{{ $codPoint['center']['lat'] }}" data-center-lng="{{ $codPoint['center']['lng'] }}">
            @csrf
            @if($simulatedPayment)
                <input type="hidden" name="qris_paid" value="1">
            @endif
            <x-form-token />
            <input type="hidden" id="meeting_latitude" name="meeting_latitude">
            <input type="hidden" id="meeting_longitude" name="meeting_longitude">
            <input type="hidden" id="buyer_latitude" name="buyer_latitude">
            <input type="hidden" id="buyer_longitude" name="buyer_longitude">

            <!-- Tahap 1: titik ketemuan & jadwal -->
            <div data-step="1" class="space-y-3">
                <div>
                    <label for="modalProductName" class="block text-sm font-semibold text-slate-700 mb-1">Barang</label>
                    <input type="text" id="modalProductName" disabled class="w-full bg-slate-100 border-slate-200 rounded-lg text-sm font-semibold text-emerald-800">
                </div>

                <!-- Saran tempat COD yang adil (App\Support\CodSpots lewat product.codSuggestions) -->
                <div>
                    <p class="block text-sm font-semibold text-slate-700 mb-1">Saran tempat ketemuan</p>
                    <p id="meetingTooFar" class="hidden mb-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700" role="alert"></p>
                    <div id="meetingSuggestions" class="space-y-1.5" role="group" aria-label="Saran tempat ketemuan"></div>
                    <p id="suggestionStatus" class="mt-1.5 text-xs text-slate-500" aria-live="polite"></p>
                </div>

                <div>
                    <p class="block text-sm font-semibold text-slate-700 mb-1" id="meetingMapLabel">Titik ketemuan</p>
                    <div id="meetingMap" class="h-56 w-full rounded-lg border border-slate-200 bg-slate-100 z-0"
                         role="application" aria-labelledby="meetingMapLabel" aria-describedby="meetingMapHint"></div>
                    <p id="meetingMapHint" class="text-xs text-slate-500 mt-1">
                        Geser pin atau ketuk peta.
                        <span id="meetingLegendSeller">🟠 lokasi acuan penjual</span>
                        <span id="meetingLegendBuyer" class="hidden">· 🔵 lokasimu</span>
                    </p>
                    <div class="mt-2 grid grid-cols-3 gap-1.5">
                        <button type="button" id="btnUseMyLocation" class="h-9 px-2 rounded-lg border border-emerald-600 text-emerald-700 hover:bg-emerald-50 text-[11px] sm:text-xs font-semibold disabled:opacity-50 disabled:cursor-not-allowed">📍 Gunakan lokasi saya</button>
                        <button type="button" id="btnMidpoint" disabled title="Aktif setelah lokasimu dan lokasi acuan penjual diketahui" class="h-9 px-2 rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-50 text-[11px] sm:text-xs font-semibold disabled:opacity-50 disabled:cursor-not-allowed">⚖️ Titik tengah</button>
                        <button type="button" id="btnSellerPoint" disabled class="h-9 px-2 rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-50 text-[11px] sm:text-xs font-semibold disabled:opacity-50 disabled:cursor-not-allowed">🏬 Pakai lokasi acuan penjual</button>
                    </div>
                    <p id="meetingDistance" class="mt-2 text-xs font-semibold text-slate-700 tabular-nums" aria-live="polite"></p>
                    <p id="meetingFairness" class="mt-1 hidden rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900" role="status"></p>
                    <p id="meetingError" class="mt-1 hidden text-xs font-semibold text-rose-600" role="alert"></p>
                </div>

                <div>
                    <div class="flex items-baseline justify-between gap-2 mb-1">
                        <label for="lokasi_cod" class="block text-sm font-semibold text-slate-700">Keterangan tempat</label>
                        <span id="lokasiCodCounter" class="text-xs text-slate-500 tabular-nums" aria-live="polite">0/{{ $noteMax }}</span>
                    </div>
                    <input type="text" id="lokasi_cod" name="lokasi_cod" maxlength="{{ $noteMax }}" placeholder="Depan Mega Mall" required
                           aria-describedby="lokasiCodCounter" class="{{ $input }}">
                </div>
                <div>
                    <label for="waktu_cod" class="block text-sm font-semibold text-slate-700 mb-1">Kapan?</label>
                    <input type="datetime-local" id="waktu_cod" name="waktu_cod" required class="{{ $input }}">
                </div>
                <button type="button" id="btnToSummary" onclick="goToBookingStep(2)" class="w-full disabled:opacity-50 disabled:cursor-not-allowed bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2.5 rounded-lg text-sm transition active:scale-[0.98] motion-reduce:transform-none">
                    Lanjut ke ringkasan
                </button>
            </div>

            <!-- Tahap 2: ringkasan sebelum membayar -->
            <div data-step="2" class="hidden space-y-3">
                <x-cod-point-map id="summaryMap" :lat="$codPoint['center']['lat']" :lng="$codPoint['center']['lng']" label="" compact />

                <dl class="rounded-xl border border-slate-200 divide-y divide-slate-100 text-sm">
                    <div class="flex justify-between gap-3 px-3 py-2"><dt class="text-slate-500">Barang</dt><dd id="summaryProduct" class="font-semibold text-slate-900 text-right"></dd></div>
                    <div class="flex justify-between gap-3 px-3 py-2"><dt class="text-slate-500">Tempat</dt><dd id="summaryLocation" class="text-slate-800 text-right break-words"></dd></div>
                    <div class="flex justify-between gap-3 px-3 py-2"><dt class="text-slate-500">Koordinat</dt><dd id="summaryCoords" class="text-slate-800 text-right font-mono text-xs"></dd></div>
                    <div class="flex justify-between gap-3 px-3 py-2"><dt class="text-slate-500">Jarak</dt><dd id="summaryDistance" class="text-slate-800 text-right tabular-nums"></dd></div>
                    <div class="flex justify-between gap-3 px-3 py-2"><dt class="text-slate-500">Jadwal</dt><dd id="summarySchedule" class="text-slate-800 text-right"></dd></div>
                    <div class="flex justify-between gap-3 px-3 py-2"><dt class="text-slate-500">Harga barang <span class="block text-[11px]">dibayar saat COD</span></dt><dd id="summaryPrice" class="text-slate-800 tabular-nums text-right"></dd></div>
                    <div class="flex justify-between gap-3 px-3 py-2 bg-emerald-50/60"><dt class="font-semibold text-slate-800">Bayar sekarang <span class="block text-[11px] font-normal text-slate-500">biaya booking via QRIS</span></dt><dd class="font-bold text-emerald-700 tabular-nums">{{ $rupiahFee }}</dd></div>
                </dl>

                <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 text-xs text-amber-900" role="note">
                    <p class="font-semibold">⚠️ Biaya booking {{ $rupiahFee }} tidak dapat dikembalikan dan menjadi milik platform.</p>
                    <p class="mt-0.5">Biaya ini menahan barang khusus untukmu sampai jadwal COD. Biaya tetap tidak dikembalikan dalam kondisi apa pun, termasuk bila kamu atau penjual membatalkan, atau penjual tidak hadir.</p>
                </div>

                <p class="text-xs text-slate-500">Belum ada biaya yang ditarik. Kamu masih bisa membatalkan di tahap ini tanpa dikenai apa pun.</p>

                <div class="flex gap-2">
                    <button type="button" onclick="goToBookingStep(1)" class="flex-1 h-10 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 text-sm font-semibold">Kembali</button>
                    @if($simulatedPayment)
                        <button type="button" onclick="goToBookingStep(3)" class="flex-[2] h-10 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold">Bayar {{ $rupiahFee }} via QRIS</button>
                    @else
                        <button type="submit" id="bookingSubmit" class="flex-[2] h-10 inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold disabled:opacity-70 disabled:cursor-wait">Bayar {{ $rupiahFee }} via QRIS</button>
                    @endif
                </div>
            </div>

            @if($simulatedPayment)
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
            @endif
        </form>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
    const bookingSubtitles = {
        1: 'Pilih titik ketemuan yang adil buat kamu dan penjual.',
        2: 'Cek lagi sebelum membayar biaya booking.',
        3: 'Bayar biaya booking supaya barangnya dikunci buat kamu.',
    };

    // --- Titik ketemuan: peta, jarak, dan pemeriksaan (server memeriksa ulang semuanya) ---
    const meeting = (function () {
        const form = document.getElementById('bookingForm');
        const areas = JSON.parse(form.dataset.areas || '[]');
        const maxEach = Number(form.dataset.maxEachKm);
        const snapKm = Number(form.dataset.snapKm);
        const spots = JSON.parse(form.dataset.spots || '[]');
        const fairKm = Number(form.dataset.fairKm);
        const TOO_FAR = 'Jarak kalian terlalu jauh untuk COD, gunakan pengiriman';
        const center = [Number(form.dataset.centerLat), Number(form.dataset.centerLng)];
        const el = id => document.getElementById(id);
        const fields = { lat: el('meeting_latitude'), lng: el('meeting_longitude'), buyerLat: el('buyer_latitude'), buyerLng: el('buyer_longitude') };

        let seller = null;      // {lat, lng, label} lokasi acuan penjual, kalau ada
        let buyer = null;       // {lat, lng} dari "Gunakan lokasi saya"; diingat selama halaman terbuka
        let point = null;       // {lat, lng} titik ketemuan
        let map = null, pin = null, sellerMarker = null, buyerMarker = null;
        let suggestUrl = null;  // endpoint saran untuk barang yang sedang dibuka
        let requestSeq = 0;     // jawaban saran yang lebih lama dari permintaan terakhir diabaikan
        let touched = false;    // pembeli sudah memilih/menggeser sendiri: saran baru tidak memindahkan pin lagi
        let autoNote = '';      // keterangan yang diisi otomatis dari nama tempat (boleh ditimpa saran berikutnya)
        let selectedName = null;
        let geoAsked = false, geoDenied = false;

        // Haversine, jari-jari bumi 6371 km (sama dengan App\Support\GeoPoint)
        function distanceKm(a, b) {
            const rad = d => d * Math.PI / 180;
            const dLat = rad(b.lat - a.lat), dLng = rad(b.lng - a.lng);
            const h = Math.sin(dLat / 2) ** 2 + Math.cos(rad(a.lat)) * Math.cos(rad(b.lat)) * Math.sin(dLng / 2) ** 2;
            return 2 * 6371 * Math.asin(Math.min(1, Math.sqrt(h)));
        }
        const round1 = km => Math.round(km * 10) / 10;
        const fmt = km => round1(km).toFixed(1);

        function inBatam(p) {
            return areas.some(a => p.lat >= a.lat[0] && p.lat <= a.lat[1] && p.lng >= a.lng[0] && p.lng <= a.lng[1]);
        }

        function distances() {
            return {
                seller: point && seller ? distanceKm(point, seller) : null,
                buyer: point && buyer ? distanceKm(point, buyer) : null,
            };
        }

        function distanceText(d, buyerLabel) {
            const parts = [];
            if (d.seller !== null) parts.push('Penjual ' + fmt(d.seller) + ' km');
            if (d.buyer !== null) parts.push(buyerLabel + ' ' + fmt(d.buyer) + ' km');
            return parts.join(' · ');
        }

        // Jarak langsung pembeli-penjual lebih dari 2 x max_each_km: tidak ada titik yang adil (sama dengan CodSpots)
        function tooFar() {
            return !!(seller && buyer && round1(distanceKm(buyer, seller)) > 2 * maxEach);
        }

        // Pesan error untuk titik saat ini, atau '' kalau boleh dipakai (sama dengan CodSpots::violation)
        function problem() {
            if (tooFar()) return TOO_FAR + '.';
            if (!point) return 'Pasang pin titik ketemuan di peta dulu.';
            if (!inBatam(point)) return 'Titik ketemuan harus berada di wilayah Batam.';
            const d = distances();
            if (d.seller !== null && round1(d.seller) > maxEach) {
                return 'Titik ketemuan terlalu jauh dari lokasi acuan penjual (' + fmt(d.seller) + ' km). Maksimal ' + maxEach + ' km untuk masing-masing pihak.';
            }
            if (d.buyer !== null && round1(d.buyer) > maxEach) {
                return 'Titik ketemuan terlalu jauh dari lokasimu (' + fmt(d.buyer) + ' km). Maksimal ' + maxEach + ' km untuk masing-masing pihak.';
            }
            return '';
        }

        function render() {
            const d = distances();
            let text = distanceText(d, 'Kamu');
            if (point && !seller) text = (text ? text + ' · ' : '') + 'Penjual belum memasang lokasi acuan';
            if (point && !buyer && seller) text += ' · Lokasimu belum diketahui';
            el('meetingDistance').textContent = point ? text : '';

            const fair = el('meetingFairness');
            const gap = d.seller !== null && d.buyer !== null ? Math.abs(d.seller - d.buyer) : 0;
            fair.classList.toggle('hidden', !(gap > fairKm));
            fair.textContent = gap > fairKm
                ? '⚠️ Jaraknya belum adil: selisih ' + fmt(gap) + ' km. Coba tombol "Titik tengah" supaya sama-sama dekat.'
                : '';

            // Terlalu jauh untuk COD: tampilkan pesan besar, sembunyikan saran, dan jangan lanjut ke ringkasan
            const far = tooFar();
            el('meetingTooFar').textContent = far
                ? '🚚 ' + TOO_FAR + '. Jarak langsung kamu ke penjual ' + fmt(distanceKm(buyer, seller)) + ' km (maks. ' + (2 * maxEach) + ' km untuk COD).'
                : '';
            el('meetingTooFar').classList.toggle('hidden', !far);
            el('meetingSuggestions').classList.toggle('hidden', far);
            el('btnToSummary').disabled = far;

            const message = point && !far ? problem() : '';
            el('meetingError').textContent = message;
            el('meetingError').classList.toggle('hidden', message === '');

            el('meetingSuggestions').querySelectorAll('[data-spot-name]').forEach(card => {
                const active = card.dataset.spotName === selectedName;
                card.setAttribute('aria-pressed', active ? 'true' : 'false');
                card.classList.toggle('border-emerald-600', active);
                card.classList.toggle('bg-emerald-50', active);
                card.classList.toggle('border-slate-200', !active);
            });

            el('btnMidpoint').disabled = !(seller && buyer) || far;
            el('btnSellerPoint').disabled = !seller;
            el('meetingLegendSeller').classList.toggle('hidden', !seller);
            el('meetingLegendBuyer').classList.toggle('hidden', !buyer);
        }

        function setPoint(lat, lng, pan, name) {
            point = { lat: lat, lng: lng };
            selectedName = name || null;
            fields.lat.value = lat.toFixed(7);
            fields.lng.value = lng.toFixed(7);
            if (pin) {
                pin.setLatLng([lat, lng]);
                pin.unbindTooltip();
                if (pan) map.panTo([lat, lng]);
            }
            render();
        }

        function setBuyer(lat, lng) {
            buyer = { lat: lat, lng: lng };
            fields.buyerLat.value = lat.toFixed(7);
            fields.buyerLng.value = lng.toFixed(7);
            if (map) {
                if (!buyerMarker) {
                    buyerMarker = L.circleMarker([lat, lng], { radius: 8, color: '#1d4ed8', fillColor: '#3b82f6', fillOpacity: 0.9, weight: 2 })
                        .bindTooltip('Lokasimu').addTo(map);
                } else {
                    buyerMarker.setLatLng([lat, lng]);
                }
            }
        }

        function ensureMap() {
            if (map || typeof L === 'undefined') return;
            const allowed = areas.length
                ? L.latLngBounds(areas.flatMap(a => [[a.lat[0], a.lng[0]], [a.lat[1], a.lng[1]]])).pad(0.15)
                : null;
            map = L.map('meetingMap', { maxBounds: allowed, maxBoundsViscosity: 1.0, minZoom: 10 }).setView(center, 12);
            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            }).addTo(map);
            pin = L.marker(center, { draggable: true, autoPan: true, keyboard: true, title: 'Titik ketemuan' }).addTo(map);
            pin.on('dragend', () => { touched = true; const p = pin.getLatLng(); setPoint(p.lat, p.lng, false); });
            map.on('click', e => { touched = true; setPoint(e.latlng.lat, e.latlng.lng, false); });
        }

        // Pilih tempat: pasang pin, dan isi keterangan dengan namanya kalau keterangan masih kosong/otomatis
        function choose(spot) {
            setPoint(spot.lat, spot.lng, true, spot.name);
            const note = el('lokasi_cod');
            if (note.value.trim() === '' || note.value === autoNote) {
                note.value = [...spot.name].slice(0, note.maxLength).join('');
                autoNote = note.value;
                updateLokasiCodCounter();
            }
        }

        function cardText(s) {
            const parts = [];
            if (s.seller_km !== null) parts.push('Penjual ' + s.seller_km.toFixed(1) + ' km');
            if (s.buyer_km !== null) parts.push('Kamu ' + s.buyer_km.toFixed(1) + ' km');
            return parts.join(' · ') || 'Dekat pusat Batam';
        }

        function showSuggestions(data) {
            const list = el('meetingSuggestions');
            list.replaceChildren();
            data.suggestions.forEach((s, i) => {
                const card = document.createElement('button');
                card.type = 'button';
                card.dataset.spotName = s.name;
                card.className = 'w-full text-left rounded-lg border-2 border-slate-200 px-3 py-2 hover:border-emerald-400 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 transition';
                const title = document.createElement('span');
                title.className = 'block text-sm font-semibold text-slate-900';
                title.textContent = (i === 0 && data.mode === 'fair' ? '⭐ ' : '') + s.name;
                const info = document.createElement('span');
                info.className = 'block text-xs text-slate-600 tabular-nums';
                info.textContent = cardText(s) + (s.diff_km !== null ? ' · selisih ' + s.diff_km.toFixed(1) + ' km' : '');
                card.append(title, info);
                card.addEventListener('click', () => { touched = true; choose(s); });
                list.append(card);
            });

            const status = {
                fair: data.suggestions.length
                    ? 'Diurutkan dari yang paling dekat untuk pihak yang terjauh, lalu selisih jarak terkecil (maks. ' + maxEach + ' km untuk masing-masing).'
                    : 'Tidak ada tempat populer dalam ' + maxEach + ' km dari kalian berdua. Geser pin di peta sendiri.',
                seller: (data.message || '') + (geoDenied ? ' Izinkan akses lokasi di browser untuk saran yang lebih adil.' : ''),
                center: 'Penjual belum memasang lokasi acuan, jadi saran diurutkan dari pusat Batam.',
            }[data.mode];
            el('suggestionStatus').textContent = data.too_far ? '' : status;

            // Saran teratas langsung dipakai, selama pembeli belum memilih sendiri
            if (!touched && !data.too_far && data.suggestions.length) choose(data.suggestions[0]);
            render();
        }

        function loadSuggestions() {
            if (!suggestUrl) return;
            const seq = ++requestSeq;
            const url = new URL(suggestUrl, window.location.href);
            if (buyer) {
                url.searchParams.set('lat', buyer.lat.toFixed(7));
                url.searchParams.set('lng', buyer.lng.toFixed(7));
            }
            el('suggestionStatus').textContent = 'Mencari saran tempat…';
            fetch(url, { headers: { Accept: 'application/json' } })
                .then(r => r.ok ? r.json() : Promise.reject(r.status))
                .then(data => { if (seq === requestSeq) showSuggestions(data); })
                .catch(() => {
                    if (seq === requestSeq) el('suggestionStatus').textContent = 'Saran tempat gagal dimuat. Geser pin di peta secara manual.';
                });
        }

        // Minta lokasi pembeli. moveTo: tombol "Gunakan lokasi saya" juga memindahkan pin ke lokasi pembeli
        function locateBuyer(moveTo) {
            if (!navigator.geolocation) {
                geoDenied = true;
                if (moveTo) {
                    el('meetingError').textContent = 'Browser ini tidak mendukung deteksi lokasi. Pilih saran atau geser pin secara manual.';
                    el('meetingError').classList.remove('hidden');
                }
                return;
            }
            const button = el('btnUseMyLocation');
            button.disabled = true;
            navigator.geolocation.getCurrentPosition(pos => {
                button.disabled = false;
                geoDenied = false;
                setBuyer(pos.coords.latitude, pos.coords.longitude);
                if (moveTo) { touched = true; setPoint(pos.coords.latitude, pos.coords.longitude, true); }
                loadSuggestions();
            }, err => {
                button.disabled = false;
                geoDenied = true;
                render();
                loadSuggestions();
                if (!moveTo) return; // permintaan otomatis yang ditolak: cukup lanjut dengan saran dari lokasi penjual
                const messages = {
                    1: 'Izin lokasi ditolak. Izinkan akses lokasi di browser, atau pilih saran/geser pin secara manual.',
                    2: 'Lokasimu tidak bisa ditemukan. Pilih saran atau geser pin secara manual.',
                    3: 'Pencarian lokasi terlalu lama. Coba lagi, atau pilih saran/geser pin secara manual.',
                };
                el('meetingError').textContent = messages[err.code] || 'Lokasimu tidak bisa dideteksi. Pilih saran atau geser pin secara manual.';
                el('meetingError').classList.remove('hidden');
            }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 });
        }

        // Dipanggil setiap popup dibuka untuk barang tertentu
        function reset(button) {
            const lat = parseFloat(button.dataset.sellerLat), lng = parseFloat(button.dataset.sellerLng);
            seller = Number.isFinite(lat) && Number.isFinite(lng) ? { lat: lat, lng: lng, label: button.dataset.sellerLabel || '' } : null;
            suggestUrl = button.dataset.suggestUrl || null;
            point = null;
            touched = false;
            autoNote = '';
            selectedName = null;
            el('meetingSuggestions').replaceChildren();
            el('suggestionStatus').textContent = '';
            fields.lat.value = fields.lng.value = '';
            // form.reset() mengosongkan lokasi pembeli; isi lagi dari lokasi yang sudah dideteksi
            if (buyer) { fields.buyerLat.value = buyer.lat.toFixed(7); fields.buyerLng.value = buyer.lng.toFixed(7); }

            ensureMap();
            if (map) {
                if (sellerMarker) { sellerMarker.remove(); sellerMarker = null; }
                if (seller) {
                    sellerMarker = L.circleMarker([seller.lat, seller.lng], { radius: 8, color: '#c2410c', fillColor: '#fb923c', fillOpacity: 0.9, weight: 2 })
                        .bindTooltip('Lokasi acuan penjual' + (seller.label ? ': ' + seller.label : '')).addTo(map);
                }
                pin.setLatLng(seller ? [seller.lat, seller.lng] : center);
                pin.bindTooltip(seller ? 'Geser ke titik ketemuan' : 'Pasang aku di titik ketemuan', { permanent: true, direction: 'top', offset: [0, -36] }).openTooltip();
                // Peta di dalam elemen yang tadinya tersembunyi perlu dihitung ulang ukurannya
                setTimeout(() => { map.invalidateSize(); map.setView(seller ? [seller.lat, seller.lng] : center, seller ? 14 : 12); }, 50);
            }

            // Ada lokasi acuan: pin mulai di sana (sudah sah). Tanpa lokasi acuan, pembeli wajib memasang pin sendiri.
            if (seller) setPoint(seller.lat, seller.lng, false); else render();

            if (!map) {
                el('meetingError').textContent = 'Peta gagal dimuat. Pilih salah satu saran tempat, atau muat ulang halaman.';
                el('meetingError').classList.remove('hidden');
            }

            // Saran dimuat dulu (dari lokasi penjual, atau lokasi pembeli yang sudah diketahui), lalu lokasi
            // pembeli diminta otomatis sekali per halaman; kalau diizinkan, saran dihitung ulang dengan adil.
            loadSuggestions();
            if (!buyer && !geoAsked) {
                geoAsked = true;
                locateBuyer(false);
            }
        }

        el('btnSellerPoint').addEventListener('click', () => {
            if (!seller) return;
            touched = true;
            setPoint(seller.lat, seller.lng, true);
        });
        el('btnMidpoint').addEventListener('click', () => {
            if (!(seller && buyer)) return;
            touched = true;
            // Rata-rata koordinat: cukup akurat untuk jarak beberapa km di Batam
            const mid = { lat: (seller.lat + buyer.lat) / 2, lng: (seller.lng + buyer.lng) / 2 };
            // Geser ke tempat populer terdekat dalam radius midpoint_snap_km (lebih mudah dicari daripada titik acak)
            const nearest = spots
                .map(s => ({ spot: s, km: distanceKm(mid, s) }))
                .filter(x => x.km <= snapKm)
                .sort((a, b) => a.km - b.km)[0];
            if (nearest) {
                choose(nearest.spot);
                el('suggestionStatus').textContent = 'Titik tengah digeser ke ' + nearest.spot.name + ' (' + fmt(nearest.km) + ' km dari titik tengah).';
            } else {
                setPoint(mid.lat, mid.lng, true);
                el('suggestionStatus').textContent = 'Tidak ada tempat populer dalam ' + snapKm + ' km dari titik tengah. Cek lagi tempatnya di peta, ya.';
            }
        });
        el('btnUseMyLocation').addEventListener('click', () => locateBuyer(true));

        // Gambar ulang peta statis ringkasan (x-cod-point-map) ke titik ketemuan
        function drawStaticMap(root, p, label) {
            const zoom = Number(root.dataset.zoom), n = 2 ** zoom;
            const x = (p.lng + 180) / 360 * n;
            const latRad = Math.max(-85.0511, Math.min(85.0511, p.lat)) * Math.PI / 180;
            const y = (1 - Math.log(Math.tan(latRad) + 1 / Math.cos(latRad)) / Math.PI) / 2 * n;
            const tx = Math.floor(x), ty = Math.floor(y);
            const grid = root.querySelector('[data-cod-map-grid]');
            grid.style.left = 'calc(50% - ' + Math.round(256 + (x - tx) * 256) + 'px)';
            grid.style.top = 'calc(50% - ' + Math.round(256 + (y - ty) * 256) + 'px)';
            grid.querySelectorAll('img').forEach(img => {
                const col = Number(img.dataset.col), row = Number(img.dataset.row);
                img.src = 'https://tile.openstreetmap.org/' + zoom + '/' + ((tx - 1 + col + n) % n) + '/' + (ty - 1 + row) + '.png';
            });
            root.querySelector('[data-cod-map-label]').textContent = label;
            root.querySelector('[data-cod-map-frame]').setAttribute('aria-label', 'Peta titik ketemuan: ' + label);
            root.querySelector('[data-cod-map-link]').href = 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(p.lat + ',' + p.lng);
        }

        return {
            reset: reset,
            problem: problem,
            summary: function (label) {
                drawStaticMap(document.getElementById('summaryMap'), point, label);
                el('summaryCoords').textContent = point.lat.toFixed(5) + ', ' + point.lng.toFixed(5);
                el('summaryDistance').textContent = distanceText(distances(), 'Kamu') || 'Belum bisa dihitung';
            },
        };
    })();

    // Penghitung karakter keterangan tempat, mis. "12/60"
    function updateLokasiCodCounter() {
        const input = document.getElementById('lokasi_cod');
        const counter = document.getElementById('lokasiCodCounter');
        const length = [...input.value].length;
        counter.textContent = length + '/' + input.maxLength;
        counter.classList.toggle('text-rose-600', length >= input.maxLength);
        counter.classList.toggle('font-semibold', length >= input.maxLength);
    }
    document.getElementById('lokasi_cod').addEventListener('input', updateLokasiCodCounter);

    function goToBookingStep(step) {
        // Titik ketemuan dan jadwal wajib valid sebelum lanjut ke ringkasan
        if (step > 1) {
            const problem = meeting.problem();
            if (problem) {
                const error = document.getElementById('meetingError');
                error.textContent = problem;
                error.classList.remove('hidden');
                error.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                return;
            }
            for (const id of ['lokasi_cod', 'waktu_cod']) {
                const field = document.getElementById(id);
                if (!field.reportValidity()) return;
            }
            const rupiah = n => 'Rp' + Number(n).toLocaleString('id-ID');
            const when = new Date(document.getElementById('waktu_cod').value);
            const label = document.getElementById('lokasi_cod').value.trim();
            document.getElementById('summaryProduct').textContent = document.getElementById('modalProductName').value;
            document.getElementById('summaryLocation').textContent = label;
            document.getElementById('summarySchedule').textContent = when.toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
            document.getElementById('summaryPrice').textContent = rupiah(document.getElementById('bookingForm').dataset.price || 0);
            meeting.summary(label);
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
        updateLokasiCodCounter();

        // Jadwal COD tidak bisa dipilih di masa lalu
        const now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        document.getElementById('waktu_cod').min = now.toISOString().slice(0, 16);

        goToBookingStep(1);
        const modal = document.getElementById('bookingModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        meeting.reset(button);
        document.getElementById('lokasi_cod').focus({ preventScroll: true });
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
        button.innerHTML = '<svg class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".3" stroke-width="3"/><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg> ' + @json($simulatedPayment ? 'Memproses pembayaran...' : 'Membuat QRIS...');
    });

    document.getElementById('bookingModal').addEventListener('click', function (e) {
        if (e.target === this) closeBookingModal();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeBookingModal();
    });
</script>

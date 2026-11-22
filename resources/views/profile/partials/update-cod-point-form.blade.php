@php
    $codErrors = $errors->codPoint;
    $addressMax = (int) config('thriftlo.cod_point.address_max');
    $center = config('thriftlo.cod_point.center');
    $address = old('cod_address', $user->cod_address);
    $lat = old('cod_latitude', $user->cod_latitude);
    $lng = old('cod_longitude', $user->cod_longitude);
    $canSetPoint = $user->isInBatam();
@endphp

<div>
    <h2 class="font-bold text-slate-900">Titik COD</h2>
    <p class="text-sm text-slate-500 mt-0.5">
        Tempat ketemuan COD yang kamu sarankan. Pembeli melihatnya sebagai peta kecil dan tombol "Buka di Google Maps" di halaman barang dan tiket booking.
    </p>

    @if (session('status') === 'cod-point-updated')
        <p class="mt-3 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800" role="status">✅ Titik COD berhasil disimpan.</p>
    @elseif (session('status') === 'cod-point-deleted')
        <p class="mt-3 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800" role="status">✅ Titik COD dihapus.</p>
    @endif

    @if($codErrors->any())
        <div class="mt-3 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2.5 text-sm text-rose-700" role="alert">
            <p class="font-semibold">Titik COD belum tersimpan:</p>
            <ul class="list-disc list-inside mt-0.5">
                @foreach($codErrors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @unless($canSetPoint)
        <p class="mt-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600">
            Lapakmu di luar Batam, jadi pembeli tidak bisa COD. Titik COD bisa diatur setelah lokasi lapak diubah ke kawasan Batam.
        </p>
    @else
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
              integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">

        <form method="post" action="{{ route('profile.cod-point.update') }}" class="mt-4 space-y-3" id="codPointForm">
            @csrf
            @method('patch')

            <div>
                <div class="flex items-center justify-between gap-2 mb-1">
                    <span class="block text-sm font-semibold text-slate-700" id="codMapLabel">Pin lokasi</span>
                    <button type="button" id="codUseMyLocation"
                            class="h-8 px-3 inline-flex items-center gap-1 rounded-lg border border-emerald-600 text-emerald-700 hover:bg-emerald-50 text-xs font-semibold disabled:opacity-60">
                        📍 Gunakan lokasi saya
                    </button>
                </div>
                <div id="codMap" class="h-64 sm:h-72 w-full rounded-lg border border-slate-200 bg-slate-100 z-0"
                     role="application" aria-labelledby="codMapLabel" aria-describedby="codMapHint"
                     data-center-lat="{{ $center['lat'] }}" data-center-lng="{{ $center['lng'] }}" data-zoom="{{ config('thriftlo.cod_point.zoom') }}"
                     data-areas="{{ json_encode(config('thriftlo.cod_point.areas')) }}"></div>
                <p id="codMapHint" class="text-xs text-slate-500 mt-1">Geser pin atau ketuk peta di titik ketemuan di wilayah Batam. Koordinat juga bisa diisi manual di bawah.</p>
                <p id="codMapStatus" class="text-xs font-semibold mt-1 hidden" role="status" aria-live="polite"></p>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="cod_latitude" class="block text-xs font-semibold text-slate-600 mb-1">Latitude</label>
                    <input type="number" id="cod_latitude" name="cod_latitude" value="{{ $lat }}" step="any" min="-90" max="90" inputmode="decimal" required
                           class="w-full rounded-lg border-slate-300 text-sm tabular-nums focus:border-emerald-500 focus:ring-emerald-500">
                </div>
                <div>
                    <label for="cod_longitude" class="block text-xs font-semibold text-slate-600 mb-1">Longitude</label>
                    <input type="number" id="cod_longitude" name="cod_longitude" value="{{ $lng }}" step="any" min="-180" max="180" inputmode="decimal" required
                           class="w-full rounded-lg border-slate-300 text-sm tabular-nums focus:border-emerald-500 focus:ring-emerald-500">
                </div>
            </div>

            <div>
                <div class="flex items-baseline justify-between gap-2 mb-1">
                    <label for="cod_address" class="block text-sm font-semibold text-slate-700">Patokan alamat</label>
                    <span id="codAddressCounter" class="text-xs text-slate-500 tabular-nums" aria-live="polite">{{ mb_strlen((string) $address) }}/{{ $addressMax }}</span>
                </div>
                <input type="text" id="cod_address" name="cod_address" value="{{ $address }}" maxlength="{{ $addressMax }}" required
                       placeholder="Contoh: Depan Mega Mall Batam Centre, dekat pintu utama"
                       aria-describedby="codAddressCounter"
                       class="w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
            </div>

            <button type="submit" class="h-9 px-4 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold">Simpan titik COD</button>
        </form>

        @if($user->cod_latitude !== null)
            <form method="post" action="{{ route('profile.cod-point.destroy') }}" class="mt-2" onsubmit="return confirm('Hapus titik COD?')">
                @csrf
                @method('delete')
                <button type="submit" class="text-sm font-semibold text-rose-600 hover:underline">Hapus titik COD</button>
            </form>
        @endif

        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
                integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
        <script>
            (function () {
                const address = document.getElementById('cod_address');
                const counter = document.getElementById('codAddressCounter');
                const max = Number(address.maxLength);

                // Penghitung karakter langsung, mis. "45/150"
                function updateCounter() {
                    const length = [...address.value].length;
                    counter.textContent = length + '/' + max;
                    counter.classList.toggle('text-rose-600', length >= max);
                    counter.classList.toggle('font-semibold', length >= max);
                }
                address.addEventListener('input', updateCounter);
                updateCounter();

                const latInput = document.getElementById('cod_latitude');
                const lngInput = document.getElementById('cod_longitude');
                const mapEl = document.getElementById('codMap');
                const status = document.getElementById('codMapStatus');
                const locateButton = document.getElementById('codUseMyLocation');

                function showStatus(message, isError) {
                    status.textContent = message;
                    status.classList.toggle('hidden', message === '');
                    status.classList.toggle('text-rose-600', !!isError);
                    status.classList.toggle('text-emerald-700', !isError);
                }

                // Sama dengan cek server (SellerLocation::isInBatamArea); server tetap penentu akhirnya
                const areas = JSON.parse(mapEl.dataset.areas || '[]');
                const outsideMessage = 'Titik COD harus berada di wilayah Batam.';

                function inBatam(lat, lng) {
                    return areas.some(function (a) {
                        return lat >= a.lat[0] && lat <= a.lat[1] && lng >= a.lng[0] && lng <= a.lng[1];
                    });
                }

                function checkArea() {
                    const lat = parseFloat(latInput.value);
                    const lng = parseFloat(lngInput.value);
                    const outside = Number.isFinite(lat) && Number.isFinite(lng) && !inBatam(lat, lng);
                    latInput.setCustomValidity(outside ? outsideMessage : '');
                    if (outside) {
                        showStatus(outsideMessage + ' Geser pin ke Batam, Belakang Padang, atau Rempang-Galang.', true);
                    } else if (status.textContent.startsWith(outsideMessage)) {
                        showStatus('', false);
                    }
                    return !outside;
                }

                latInput.addEventListener('input', checkArea);
                lngInput.addEventListener('input', checkArea);
                latInput.form.addEventListener('submit', function (e) {
                    if (!checkArea()) {
                        e.preventDefault();
                        latInput.reportValidity();
                    }
                });

                if (typeof L === 'undefined') {
                    mapEl.classList.add('hidden');
                    locateButton.classList.add('hidden');
                    showStatus('Peta gagal dimuat. Isi latitude dan longitude secara manual.', true);
                    return;
                }

                // Nilai lama yang tidak valid (mis. setelah gagal validasi) tidak dipakai sebagai titik awal
                const oldLat = parseFloat(latInput.value);
                const oldLng = parseFloat(lngInput.value);
                const hasPoint = Number.isFinite(oldLat) && Number.isFinite(oldLng) && Math.abs(oldLat) <= 90 && Math.abs(oldLng) <= 180;
                const start = hasPoint
                    ? [oldLat, oldLng]
                    : [Number(mapEl.dataset.centerLat), Number(mapEl.dataset.centerLng)];

                // Peta tidak bisa digeser jauh dari Batam (gabungan semua kotak wilayah + sedikit ruang)
                const allowed = areas.length
                    ? L.latLngBounds(areas.flatMap(function (a) { return [[a.lat[0], a.lng[0]], [a.lat[1], a.lng[1]]]; })).pad(0.15)
                    : null;
                const map = L.map(mapEl, { maxBounds: allowed, maxBoundsViscosity: 1.0, minZoom: 10 })
                    .setView(start, hasPoint ? 17 : Number(mapEl.dataset.zoom));
                L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                }).addTo(map);

                const marker = L.marker(start, { draggable: true, autoPan: true, keyboard: true, title: 'Pin titik COD' }).addTo(map);
                if (!hasPoint) {
                    marker.bindTooltip('Geser aku ke titik COD', { permanent: true, direction: 'top', offset: [0, -36] }).openTooltip();
                }

                function setPoint(latlng, pan) {
                    const lat = Math.max(-90, Math.min(90, latlng.lat));
                    const lng = ((latlng.lng + 540) % 360) - 180; // bungkus longitude ke -180..180
                    marker.setLatLng([lat, lng]);
                    marker.unbindTooltip();
                    latInput.value = lat.toFixed(7);
                    lngInput.value = lng.toFixed(7);
                    if (pan) map.setView([lat, lng], Math.max(map.getZoom(), 17));
                    return checkArea();
                }

                marker.on('dragend', function () { setPoint(marker.getLatLng(), false); });
                map.on('click', function (e) { setPoint(e.latlng, false); });

                // Koordinat yang diketik manual ikut menggeser pin
                function syncFromInputs() {
                    const lat = parseFloat(latInput.value);
                    const lng = parseFloat(lngInput.value);
                    if (Number.isFinite(lat) && Number.isFinite(lng) && Math.abs(lat) <= 90 && Math.abs(lng) <= 180) {
                        marker.setLatLng([lat, lng]);
                        marker.unbindTooltip();
                        map.panTo([lat, lng]);
                    }
                }
                latInput.addEventListener('change', syncFromInputs);
                lngInput.addEventListener('change', syncFromInputs);

                locateButton.addEventListener('click', function () {
                    if (!navigator.geolocation) {
                        showStatus('Browser ini tidak mendukung deteksi lokasi. Geser pin secara manual.', true);
                        return;
                    }
                    locateButton.disabled = true;
                    showStatus('Mencari lokasimu…', false);
                    navigator.geolocation.getCurrentPosition(function (pos) {
                        locateButton.disabled = false;
                        if (setPoint(L.latLng(pos.coords.latitude, pos.coords.longitude), true)) {
                            showStatus('Pin dipindah ke lokasimu. Geser lagi kalau kurang pas.', false);
                        }
                    }, function (err) {
                        locateButton.disabled = false;
                        const messages = {
                            1: 'Izin lokasi ditolak. Izinkan akses lokasi di browser, atau geser pin secara manual.',
                            2: 'Lokasi tidak bisa ditemukan. Geser pin secara manual.',
                            3: 'Pencarian lokasi terlalu lama. Coba lagi atau geser pin secara manual.',
                        };
                        showStatus(messages[err.code] || 'Lokasi tidak bisa dideteksi. Geser pin secara manual.', true);
                    }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 });
                });
            })();
        </script>
    @endunless
</div>

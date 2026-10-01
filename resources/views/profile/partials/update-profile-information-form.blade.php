@php
    $input = 'w-full border-slate-300 rounded-lg text-sm focus:border-emerald-500 focus:ring-emerald-500';
@endphp

<div>
    <h2 class="font-bold text-slate-900">Informasi akun</h2>
    <p class="text-sm text-slate-500 mt-0.5">Data ini dipakai penjual atau pembeli untuk menghubungimu saat COD.</p>

    @if (session('status') === 'profile-updated')
        <p class="mt-3 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800" role="status">✅ Profil berhasil disimpan.</p>
    @endif

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-4 space-y-4">
        @csrf
        @method('patch')

        <div>
            <label for="name" class="block text-sm font-semibold text-slate-700 mb-1">Nama lengkap</label>
            <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required autocomplete="name" class="{{ $input }}">
            <x-input-error class="mt-1" :messages="$errors->get('name')" />
        </div>

        <div>
            <label for="email" class="block text-sm font-semibold text-slate-700 mb-1">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required autocomplete="username" class="{{ $input }}">
            <x-input-error class="mt-1" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <p class="text-sm mt-2 text-slate-700">
                    Email kamu belum terverifikasi.
                    <button form="send-verification" class="underline text-emerald-700 hover:text-emerald-800">Kirim ulang email verifikasi</button>
                </p>
                @if (session('status') === 'verification-link-sent')
                    <p class="mt-1 text-sm text-emerald-700">Link verifikasi baru sudah dikirim ke emailmu.</p>
                @endif
            @endif
        </div>

        <div>
            <label for="phone_number" class="block text-sm font-semibold text-slate-700 mb-1">Nomor WhatsApp</label>
            <input id="phone_number" name="phone_number" type="tel" inputmode="tel" value="{{ old('phone_number', $user->phone_number) }}" maxlength="20" autocomplete="tel" placeholder="0812xxxxxxxx" class="{{ $input }}">
            <x-input-error class="mt-1" :messages="$errors->get('phone_number')" />
        </div>

        @if ($user->role === 'penjual')
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label for="nama_toko" class="block text-sm font-semibold text-slate-700 mb-1">Nama toko</label>
                    <input id="nama_toko" name="nama_toko" type="text" value="{{ old('nama_toko', $user->nama_toko) }}" required maxlength="255" class="{{ $input }}">
                    <x-input-error class="mt-1" :messages="$errors->get('nama_toko')" />
                </div>
                <div>
                    @php $currentLocation = old('lokasi_lapak', $user->lokasi_lapak); @endphp
                    <label for="lokasi_lapak" class="block text-sm font-semibold text-slate-700 mb-1">Lokasi lapak</label>
                    <select id="lokasi_lapak" name="lokasi_lapak" required class="{{ $input }}">
                        <option value="" disabled @selected(!in_array($currentLocation, \App\Support\SellerLocation::options(), true))>Pilih kawasan lapakmu</option>
                        <optgroup label="Batam (bisa COD)">
                            @foreach(config('thriftlo.batam_areas') as $area)
                                <option value="{{ $area }}" @selected($currentLocation === $area)>{{ $area }}</option>
                            @endforeach
                        </optgroup>
                        <option value="{{ \App\Support\SellerLocation::OUTSIDE_BATAM }}" @selected($currentLocation === \App\Support\SellerLocation::OUTSIDE_BATAM)>Luar Batam (hanya pengiriman)</option>
                    </select>
                    <p class="mt-1 text-xs text-slate-500">Lapak di Batam bisa COD. Luar Batam hanya bisa lewat pengiriman.</p>
                    @php $outside = $currentLocation === \App\Support\SellerLocation::OUTSIDE_BATAM; @endphp
                    <div id="kotaLapakField" class="mt-3 {{ $outside ? '' : 'hidden' }}">
                        <label for="kota_lapak" class="block text-sm font-semibold text-slate-700 mb-1">Kota asal lapak</label>
                        <input id="kota_lapak" name="kota_lapak" type="text" value="{{ old('kota_lapak', $user->kota_lapak) }}" maxlength="100" placeholder="Contoh: Surabaya" @if($outside) required @endif class="{{ $input }}">
                        <x-input-error class="mt-1" :messages="$errors->get('kota_lapak')" />
                    </div>
                    <script>
                        // Kolom kota hanya untuk lapak Luar Batam
                        document.getElementById('lokasi_lapak').addEventListener('change', function () {
                            const outside = this.value === @json(\App\Support\SellerLocation::OUTSIDE_BATAM);
                            document.getElementById('kotaLapakField').classList.toggle('hidden', !outside);
                            document.getElementById('kota_lapak').required = outside;
                        });
                    </script>
                    <x-input-error class="mt-1" :messages="$errors->get('lokasi_lapak')" />
                </div>
            </div>
        @endif

        <button type="submit" class="px-5 h-10 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold">Simpan perubahan</button>
    </form>
</div>

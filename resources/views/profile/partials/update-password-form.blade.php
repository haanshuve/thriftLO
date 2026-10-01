@php
    $input = 'w-full border-slate-300 rounded-lg text-sm focus:border-emerald-500 focus:ring-emerald-500';
@endphp

<div>
    <h2 class="font-bold text-slate-900">Ubah kata sandi</h2>
    <p class="text-sm text-slate-500 mt-0.5">Gunakan kata sandi yang panjang dan tidak dipakai di tempat lain.</p>

    @if ($user->google_id)
        <p class="mt-3 rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-sm text-sky-800">
            Daftar lewat Google dan belum pernah membuat kata sandi? Keluar dulu, lalu pakai <strong>Lupa Sandi?</strong> di halaman masuk untuk membuatnya.
        </p>
    @endif

    @if (session('status') === 'password-updated')
        <p class="mt-3 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800" role="status">✅ Kata sandi berhasil diubah.</p>
    @endif

    <form method="post" action="{{ route('password.update') }}" class="mt-4 space-y-4">
        @csrf
        @method('put')

        <div>
            <label for="update_password_current_password" class="block text-sm font-semibold text-slate-700 mb-1">Kata sandi saat ini</label>
            <input id="update_password_current_password" name="current_password" type="password" autocomplete="current-password" class="{{ $input }}">
            <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-1" />
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label for="update_password_password" class="block text-sm font-semibold text-slate-700 mb-1">Kata sandi baru</label>
                <input id="update_password_password" name="password" type="password" autocomplete="new-password" class="{{ $input }}">
                <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-1" />
            </div>
            <div>
                <label for="update_password_password_confirmation" class="block text-sm font-semibold text-slate-700 mb-1">Ulangi kata sandi baru</label>
                <input id="update_password_password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" class="{{ $input }}">
                <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-1" />
            </div>
        </div>

        <button type="submit" class="px-5 h-10 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold">Ubah kata sandi</button>
    </form>
</div>

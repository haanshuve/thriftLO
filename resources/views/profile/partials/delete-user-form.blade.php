<details class="group" @if($errors->userDeletion->isNotEmpty()) open @endif>
    <summary class="list-none cursor-pointer select-none flex items-center justify-between">
        <span>
            <span class="block font-bold text-rose-700">Hapus akun</span>
            <span class="block text-sm text-slate-500 mt-0.5">Semua data akun, barang, booking, dan chat akan dihapus permanen.</span>
        </span>
        <svg class="w-4 h-4 text-slate-400 shrink-0 transition group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
    </summary>

    <form method="post" action="{{ route('profile.destroy') }}" class="mt-4 space-y-3">
        @csrf
        @method('delete')

        <p class="text-sm text-slate-700">Tindakan ini tidak bisa dibatalkan. Masukkan kata sandimu untuk mengonfirmasi.</p>

        <div>
            <label for="delete_password" class="block text-sm font-semibold text-slate-700 mb-1">Kata sandi</label>
            <input id="delete_password" name="password" type="password" autocomplete="current-password" required
                   class="w-full sm:w-80 border-slate-300 rounded-lg text-sm focus:border-rose-500 focus:ring-rose-500">
            <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-1" />
        </div>

        <button type="submit" class="px-5 h-10 rounded-lg bg-rose-600 hover:bg-rose-700 text-white text-sm font-semibold">Hapus akun saya permanen</button>
    </form>
</details>

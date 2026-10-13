@php
    $avatarError = $errors->avatar->first('avatar');
@endphp

<div>
    <h2 class="font-bold text-slate-900">Foto profil</h2>
    <p class="text-sm text-slate-500 mt-0.5">Tampil di menu akun, chat, dan halaman toko. JPG, PNG, atau WEBP, maksimal 2MB.</p>

    @if (session('status') === 'avatar-updated')
        <p class="mt-3 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800" role="status">✅ Foto profil berhasil diperbarui.</p>
    @elseif (session('status') === 'avatar-deleted')
        <p class="mt-3 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800" role="status">✅ Foto profil dihapus.</p>
    @endif

    <div class="mt-4 flex flex-col sm:flex-row sm:items-center gap-4">
        <x-avatar :user="$user" size="2xl" />

        <div class="flex-1 min-w-0 space-y-2">
            <form method="post" action="{{ route('profile.avatar.update') }}" enctype="multipart/form-data" class="flex flex-col sm:flex-row gap-2">
                @csrf
                <label for="avatar" class="sr-only">Pilih foto profil</label>
                <input type="file" id="avatar" name="avatar" accept="image/jpeg,image/png,image/webp" required
                       data-max-bytes="{{ 2 * 1024 * 1024 }}" aria-describedby="avatar_error"
                       class="flex-1 min-w-0 text-sm text-slate-500 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100">
                <button type="submit" class="shrink-0 h-9 px-4 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold">Unggah foto</button>
            </form>

            <p id="avatar_error" class="text-xs font-semibold text-rose-600 {{ $avatarError ? '' : 'hidden' }}" role="alert">{{ $avatarError }}</p>

            @if($user->avatar_path)
                <form method="post" action="{{ route('profile.avatar.destroy') }}" onsubmit="return confirm('Hapus foto profil?')">
                    @csrf
                    @method('delete')
                    <button type="submit" class="text-sm font-semibold text-rose-600 hover:underline">Hapus foto</button>
                </form>
            @elseif($user->google_avatar_url)
                <p class="text-xs text-slate-500">Saat ini memakai foto dari akun Google.</p>
            @endif
        </div>
    </div>
</div>

<script>
    // Beri tahu langsung kalau foto terlalu besar atau formatnya salah, sebelum diunggah
    (function () {
        const input = document.getElementById('avatar');
        const error = document.getElementById('avatar_error');

        input.addEventListener('change', function () {
            const file = input.files[0];
            let message = '';
            if (file && !['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
                message = 'Foto profil harus berformat JPG, PNG, atau WEBP.';
            } else if (file && file.size > Number(input.dataset.maxBytes)) {
                message = 'Ukuran foto profil maksimal 2MB.';
            }
            input.setCustomValidity(message);
            error.textContent = message;
            error.classList.toggle('hidden', message === '');
        });
    })();
</script>

<x-admin-layout title="Kategori">

    @php
        $input = 'w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500';
        $editingId = (int) old('category_id');
    @endphp

    <div>
        <h1 class="text-lg sm:text-xl font-bold text-slate-900">Kategori</h1>
        <p class="text-sm text-slate-500 mt-0.5">Kategori tampil sebagai chip di katalog dan pilihan saat penjual menayangkan barang. Kategori yang masih dipakai tidak bisa dihapus.</p>
    </div>

    <!-- Tambah kategori -->
    <section aria-labelledby="judulTambah" class="rounded-xl bg-white border border-slate-200 p-4">
        <h2 id="judulTambah" class="text-sm font-bold text-slate-900 mb-3">Tambah kategori</h2>
        <form action="{{ route('admin.categories.store') }}" method="POST" class="grid grid-cols-[4.5rem_1fr] sm:grid-cols-[4.5rem_1fr_6rem_auto] gap-2 sm:gap-3 items-end">
            @csrf
            <div>
                <label for="new-icon" class="block text-xs font-semibold text-slate-600 mb-1">Ikon</label>
                <input type="text" id="new-icon" name="icon" value="{{ old('category_id') ? '' : old('icon') }}" maxlength="16" placeholder="📦" class="{{ $input }} text-center">
            </div>
            <div>
                <label for="new-label" class="block text-xs font-semibold text-slate-600 mb-1">Nama kategori</label>
                <input type="text" id="new-label" name="label" value="{{ old('category_id') ? '' : old('label') }}" maxlength="50" required placeholder="Mis. Mainan" class="{{ $input }}">
            </div>
            <div>
                <label for="new-order" class="block text-xs font-semibold text-slate-600 mb-1">Urutan</label>
                <input type="number" id="new-order" name="sort_order" value="{{ old('category_id') ? '' : old('sort_order') }}" min="0" max="999" placeholder="Auto" class="{{ $input }}">
            </div>
            <button type="submit" class="h-[38px] px-4 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold">Tambah</button>
        </form>
        @if($errors->createCategory->any())
            <p class="mt-2 text-sm text-rose-600">{{ $errors->createCategory->first() }}</p>
        @endif
    </section>

    <!-- Daftar kategori -->
    <section aria-labelledby="judulDaftar" class="rounded-xl bg-white border border-slate-200 overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-200 flex items-center justify-between">
            <h2 id="judulDaftar" class="text-sm font-bold text-slate-900">Daftar kategori</h2>
            <span class="text-xs text-slate-500">{{ $categories->count() }} kategori</span>
        </div>
        <ul class="divide-y divide-slate-100">
            @foreach($categories as $category)
                @php $used = (int) ($productCounts[$category->slug] ?? 0); @endphp
                <li class="p-4" x-data="{ edit: {{ $editingId === $category->id ? 'true' : 'false' }} }">
                    <div class="flex items-center gap-3" x-show="!edit">
                        <span class="w-10 h-10 shrink-0 rounded-lg bg-slate-100 flex items-center justify-center text-xl" aria-hidden="true">{{ $category->icon }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-slate-900">{{ $category->label }}</p>
                            <p class="text-xs text-slate-500">Urutan {{ $category->sort_order }} · {{ $used }} produk · kode <code class="font-mono">{{ $category->slug }}</code></p>
                        </div>
                        <button type="button" @click="edit = true" class="h-9 px-3 rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-50 text-sm font-semibold">Ubah</button>
                        <form action="{{ route('admin.categories.destroy', $category) }}" method="POST"
                              onsubmit="return confirm('Hapus kategori {{ addslashes($category->label) }}?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="h-9 px-3 rounded-lg border border-rose-300 text-rose-700 hover:bg-rose-50 text-sm font-semibold disabled:opacity-40 disabled:cursor-not-allowed"
                                    @if($used > 0) disabled title="Masih dipakai {{ $used }} produk" @endif>Hapus</button>
                        </form>
                    </div>

                    <form x-show="edit" @if($editingId !== $category->id) style="display: none" @endif
                          action="{{ route('admin.categories.update', $category) }}" method="POST"
                          class="grid grid-cols-[4.5rem_1fr] sm:grid-cols-[4.5rem_1fr_6rem_auto] gap-2 sm:gap-3 items-end">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="category_id" value="{{ $category->id }}">
                        @php $isFailed = $editingId === $category->id; @endphp
                        <div>
                            <label for="icon-{{ $category->id }}" class="block text-xs font-semibold text-slate-600 mb-1">Ikon</label>
                            <input type="text" id="icon-{{ $category->id }}" name="icon" value="{{ $isFailed ? old('icon') : $category->icon }}" maxlength="16" class="{{ $input }} text-center">
                        </div>
                        <div>
                            <label for="label-{{ $category->id }}" class="block text-xs font-semibold text-slate-600 mb-1">Nama kategori</label>
                            <input type="text" id="label-{{ $category->id }}" name="label" value="{{ $isFailed ? old('label') : $category->label }}" maxlength="50" required class="{{ $input }}">
                        </div>
                        <div>
                            <label for="order-{{ $category->id }}" class="block text-xs font-semibold text-slate-600 mb-1">Urutan</label>
                            <input type="number" id="order-{{ $category->id }}" name="sort_order" value="{{ $isFailed ? old('sort_order') : $category->sort_order }}" min="0" max="999" class="{{ $input }}">
                        </div>
                        <div class="col-span-2 sm:col-span-1 flex gap-2 justify-end">
                            <button type="button" @click="edit = false" class="h-[38px] px-3 rounded-lg text-slate-600 hover:bg-slate-100 text-sm font-semibold">Batal</button>
                            <button type="submit" class="h-[38px] px-4 rounded-lg bg-slate-900 hover:bg-slate-700 text-white text-sm font-semibold">Simpan</button>
                        </div>
                        @if($isFailed && $errors->editCategory->any())
                            <p class="col-span-full text-sm text-rose-600">{{ $errors->editCategory->first() }}</p>
                        @endif
                    </form>
                </li>
            @endforeach
        </ul>
    </section>
</x-admin-layout>

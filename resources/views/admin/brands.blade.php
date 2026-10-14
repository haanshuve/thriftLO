<x-admin-layout title="Merek">

    @php
        $input = 'w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500';
        $editingId = (int) old('brand_id');
    @endphp

    <div>
        <h1 class="text-lg sm:text-xl font-bold text-slate-900">Merek</h1>
        <p class="text-sm text-slate-500 mt-0.5">Daftar merek untuk form jual, beranda, dan halaman merek. Merek yang masih dipakai produk tidak bisa dihapus, cukup dinonaktifkan.</p>
    </div>

    <!-- Tambah merek -->
    <section aria-labelledby="judulTambah" class="rounded-xl bg-white border border-slate-200 p-4">
        <h2 id="judulTambah" class="text-sm font-bold text-slate-900 mb-3">Tambah merek</h2>
        <form action="{{ route('admin.brands.store') }}" method="POST" class="grid grid-cols-[1fr_6rem] sm:grid-cols-[1fr_6rem_auto_auto] gap-2 sm:gap-3 items-end">
            @csrf
            <div>
                <label for="new-name" class="block text-xs font-semibold text-slate-600 mb-1">Nama merek</label>
                <input type="text" id="new-name" name="name" value="{{ $editingId ? '' : old('name') }}" maxlength="60" required placeholder="Mis. Kickers" class="{{ $input }}">
            </div>
            <div>
                <label for="new-order" class="block text-xs font-semibold text-slate-600 mb-1">Urutan</label>
                <input type="number" id="new-order" name="sort_order" value="{{ $editingId ? '' : old('sort_order') }}" min="0" max="999" placeholder="Auto" class="{{ $input }}">
            </div>
            <label class="h-[38px] inline-flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" name="is_popular" value="1" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" @checked(!$editingId && old('is_popular'))>
                Populer
            </label>
            <button type="submit" class="h-[38px] px-4 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold">Tambah</button>
        </form>
        @if($errors->createBrand->any())
            <p class="mt-2 text-sm text-rose-600">{{ $errors->createBrand->first() }}</p>
        @endif
    </section>

    <form method="GET" action="{{ route('admin.brands') }}" class="flex gap-2" role="search">
        <label for="q" class="sr-only">Cari merek</label>
        <input type="search" id="q" name="q" value="{{ $search }}" placeholder="Cari merek" class="flex-1 {{ $input }}">
        <button type="submit" class="h-[38px] px-4 rounded-lg bg-slate-900 hover:bg-slate-700 text-white text-sm font-semibold">Cari</button>
    </form>

    <!-- Daftar merek -->
    <section aria-labelledby="judulDaftar" class="rounded-xl bg-white border border-slate-200 overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-200 flex items-center justify-between">
            <h2 id="judulDaftar" class="text-sm font-bold text-slate-900">Daftar merek</h2>
            <span class="text-xs text-slate-500">{{ $brands->count() }} merek</span>
        </div>
        <ul class="divide-y divide-slate-100">
            @forelse($brands as $brand)
                @php $isFailed = $editingId === $brand->id; @endphp
                <li class="p-4 {{ $brand->is_active ? '' : 'bg-slate-50' }}" x-data="{ edit: {{ $isFailed ? 'true' : 'false' }} }">
                    <div class="flex flex-wrap items-center gap-3" x-show="!edit">
                        <x-brand-wordmark :brand="$brand" size="sm" class="{{ $brand->is_active ? '' : 'opacity-50' }}" />
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-slate-900">
                                {{ $brand->name }}
                                @if($brand->is_popular)<span class="ml-1 text-[11px] font-semibold px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-800">Populer</span>@endif
                                @unless($brand->is_active)<span class="ml-1 text-[11px] font-semibold px-1.5 py-0.5 rounded-full bg-slate-200 text-slate-700">Nonaktif</span>@endunless
                            </p>
                            <p class="text-xs text-slate-500">Urutan {{ $brand->sort_order }} · {{ $brand->products_count }} produk · <code class="font-mono">/merek/{{ $brand->slug }}</code></p>
                        </div>
                        <div class="w-full sm:w-auto flex justify-end gap-2">
                            <button type="button" @click="edit = true" class="h-9 px-3 rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-50 text-sm font-semibold">Ubah</button>
                            <form action="{{ route('admin.brands.toggle', $brand) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="h-9 px-3 rounded-lg border text-sm font-semibold {{ $brand->is_active ? 'border-amber-300 text-amber-800 hover:bg-amber-50' : 'border-emerald-600 text-emerald-700 hover:bg-emerald-50' }}">
                                    {{ $brand->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                </button>
                            </form>
                            <form action="{{ route('admin.brands.destroy', $brand) }}" method="POST" onsubmit="return confirm('Hapus merek {{ addslashes($brand->name) }}?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="h-9 px-3 rounded-lg border border-rose-300 text-rose-700 hover:bg-rose-50 text-sm font-semibold disabled:opacity-40 disabled:cursor-not-allowed"
                                        @if($brand->products_count > 0) disabled title="Masih dipakai {{ $brand->products_count }} produk" @endif>Hapus</button>
                            </form>
                        </div>
                    </div>

                    <form x-show="edit" @unless($isFailed) style="display: none" @endunless
                          action="{{ route('admin.brands.update', $brand) }}" method="POST"
                          class="grid grid-cols-[1fr_6rem] sm:grid-cols-[1fr_6rem_auto_auto] gap-2 sm:gap-3 items-end">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="brand_id" value="{{ $brand->id }}">
                        <div>
                            <label for="name-{{ $brand->id }}" class="block text-xs font-semibold text-slate-600 mb-1">Nama merek</label>
                            <input type="text" id="name-{{ $brand->id }}" name="name" value="{{ $isFailed ? old('name') : $brand->name }}" maxlength="60" required class="{{ $input }}">
                        </div>
                        <div>
                            <label for="order-{{ $brand->id }}" class="block text-xs font-semibold text-slate-600 mb-1">Urutan</label>
                            <input type="number" id="order-{{ $brand->id }}" name="sort_order" value="{{ $isFailed ? old('sort_order') : $brand->sort_order }}" min="0" max="999" class="{{ $input }}">
                        </div>
                        <label class="h-[38px] inline-flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" name="is_popular" value="1" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" @checked($isFailed ? old('is_popular') : $brand->is_popular)>
                            Populer
                        </label>
                        <div class="flex gap-2 justify-end">
                            <button type="button" @click="edit = false" class="h-[38px] px-3 rounded-lg text-slate-600 hover:bg-slate-100 text-sm font-semibold">Batal</button>
                            <button type="submit" class="h-[38px] px-4 rounded-lg bg-slate-900 hover:bg-slate-700 text-white text-sm font-semibold">Simpan</button>
                        </div>
                        @if($isFailed && $errors->editBrand->any())
                            <p class="col-span-full text-sm text-rose-600">{{ $errors->editBrand->first() }}</p>
                        @endif
                    </form>
                </li>
            @empty
                <li class="px-4 py-12 text-center text-sm text-slate-500">Tidak ada merek yang cocok.</li>
            @endforelse
        </ul>
    </section>
</x-admin-layout>

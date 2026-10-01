{{-- Field produk untuk form upload (modal dashboard) dan edit. Butuh: $product (null saat upload), $sellerInBatam --}}
@php
    $shippingRows = old('shipping_options', $product?->shipping_options ?? []);
    if (empty($shippingRows)) {
        $shippingRows = [['courier' => '', 'cost' => '']];
    }
    $input = 'w-full border-slate-300 rounded-lg text-sm focus:border-emerald-500 focus:ring-emerald-500';
@endphp

<div>
    <label for="nama_barang" class="block text-sm font-semibold text-slate-700 mb-1">Nama barang</label>
    <input type="text" id="nama_barang" name="nama_barang" value="{{ old('nama_barang', $product?->title) }}" maxlength="255" required placeholder="Contoh: Jaket denim Levi's ukuran L" class="{{ $input }}">
</div>

<div class="grid grid-cols-2 gap-3">
    <div>
        <label for="kategori" class="block text-sm font-semibold text-slate-700 mb-1">Kategori</label>
        <select id="kategori" name="kategori" required class="{{ $input }}">
            @foreach(config('thriftlo.categories') as $value => $cat)
                <option value="{{ $value }}" @selected(old('kategori', $product?->kategori) === $value)>{{ $cat['icon'] }} {{ $cat['label'] }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="mode_jual" class="block text-sm font-semibold text-slate-700 mb-1">Mode jual</label>
        <select id="mode_jual" name="mode_jual" class="{{ $input }}">
            <option value="ecer" @selected(old('mode_jual', $product?->mode_jual ?? 'ecer') === 'ecer')>Eceran (satuan)</option>
            <option value="borongan" @selected(old('mode_jual', $product?->mode_jual) === 'borongan')>Borongan / paket</option>
        </select>
    </div>
</div>

<div class="grid grid-cols-2 gap-3">
    <div>
        <label for="harga" class="block text-sm font-semibold text-slate-700 mb-1">Harga (Rp)</label>
        <input type="number" id="harga" name="harga" value="{{ old('harga', $product ? (int) $product->price : null) }}" min="0" step="500" inputmode="numeric" required placeholder="150000" class="{{ $input }}">
    </div>
    <div>
        <label for="grade" class="block text-sm font-semibold text-slate-700 mb-1">Kondisi</label>
        <select id="grade" name="grade" class="{{ $input }}">
            @foreach(config('thriftlo.grades') as $grade)
                <option value="{{ $grade }}" @selected(old('grade', $product?->grade) === $grade)>{{ $grade }}</option>
            @endforeach
        </select>
    </div>
</div>

<div>
    <label for="image" class="block text-sm font-semibold text-slate-700 mb-1">
        Foto produk @if($product)<span class="font-normal text-slate-400">(kosongkan kalau tidak diganti)</span>@endif
    </label>
    <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp" @unless($product) required @endunless
           class="w-full text-sm text-slate-500 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100">
    <p class="text-xs text-slate-400 mt-1">JPG, PNG, atau WEBP, maksimal 5MB. Foto persegi tampil paling bagus di katalog.</p>
</div>

<div>
    <label for="video_proof_url" class="block text-sm font-semibold text-slate-700 mb-1">Link video proof <span class="font-normal text-slate-400">(opsional)</span></label>
    <input type="url" id="video_proof_url" name="video_proof_url" value="{{ old('video_proof_url', $product?->video_proof) }}" placeholder="https://youtube.com/..." class="{{ $input }}">
    <p class="text-xs text-slate-400 mt-1">Video kondisi asli barang membuat pembeli lebih percaya.</p>
</div>

<div>
    <label for="deskripsi" class="block text-sm font-semibold text-slate-700 mb-1">Deskripsi <span class="font-normal text-slate-400">(opsional)</span></label>
    <textarea id="deskripsi" name="deskripsi" rows="3" maxlength="2000" placeholder="Ukuran, minus, kelengkapan, dan alasan dijual..." class="{{ $input }}">{{ old('deskripsi', $product?->description) }}</textarea>
</div>

<!-- Opsi pengiriman -->
<fieldset class="rounded-lg border border-slate-200 p-3">
    <legend class="px-1 text-sm font-semibold text-slate-700">
        Opsi pengiriman
        <span class="font-normal {{ $sellerInBatam ? 'text-slate-400' : 'text-rose-600' }}">{{ $sellerInBatam ? '(opsional)' : '(wajib, minimal 1)' }}</span>
    </legend>
    <p class="text-xs text-slate-500 mb-2">
        {{ $sellerInBatam
            ? 'Lapakmu di Batam, jadi pembeli bisa COD. Tambahkan pengiriman kalau mau melayani pembeli dari luar Batam.'
            : 'Lapakmu di luar Batam, jadi pembeli tidak bisa COD dan hanya bisa membeli lewat pengiriman.' }}
    </p>

    <div id="shippingRows" class="space-y-2">
        @foreach(array_values($shippingRows) as $i => $row)
            <div class="shipping-row flex gap-2">
                <input type="text" name="shipping_options[{{ $i }}][courier]" value="{{ $row['courier'] ?? '' }}" maxlength="60" placeholder="Kurir, mis. JNE Reguler" aria-label="Nama kurir" class="flex-1 min-w-0 {{ $input }}">
                <input type="number" name="shipping_options[{{ $i }}][cost]" value="{{ $row['cost'] ?? '' }}" min="0" step="500" inputmode="numeric" placeholder="Ongkir (Rp)" aria-label="Ongkir (Rp)" class="w-28 sm:w-32 {{ $input }}">
                <button type="button" onclick="removeShippingRow(this)" class="shrink-0 w-9 rounded-lg text-slate-400 hover:bg-rose-50 hover:text-rose-600 text-lg" aria-label="Hapus opsi pengiriman">&times;</button>
            </div>
        @endforeach
    </div>
    <button type="button" onclick="addShippingRow()" class="mt-2 text-sm font-semibold text-emerald-700 hover:underline">+ Tambah opsi pengiriman</button>
</fieldset>

<template id="shippingRowTemplate">
    <div class="shipping-row flex gap-2">
        <input type="text" name="shipping_options[__INDEX__][courier]" maxlength="60" placeholder="Kurir, mis. J&T Ekonomi" aria-label="Nama kurir" class="flex-1 min-w-0 {{ $input }}">
        <input type="number" name="shipping_options[__INDEX__][cost]" min="0" step="500" inputmode="numeric" placeholder="Ongkir (Rp)" aria-label="Ongkir (Rp)" class="w-28 sm:w-32 {{ $input }}">
        <button type="button" onclick="removeShippingRow(this)" class="shrink-0 w-9 rounded-lg text-slate-400 hover:bg-rose-50 hover:text-rose-600 text-lg" aria-label="Hapus opsi pengiriman">&times;</button>
    </div>
</template>

<script>
    let shippingRowIndex = {{ count($shippingRows) }};

    function addShippingRow() {
        const html = document.getElementById('shippingRowTemplate').innerHTML.replaceAll('__INDEX__', shippingRowIndex++);
        document.getElementById('shippingRows').insertAdjacentHTML('beforeend', html);
        document.querySelector('#shippingRows .shipping-row:last-child input').focus();
    }

    function removeShippingRow(button) {
        button.closest('.shipping-row').remove();
        // Selalu sisakan satu baris kosong supaya form tetap bisa diisi
        if (!document.querySelector('#shippingRows .shipping-row')) addShippingRow();
    }
</script>

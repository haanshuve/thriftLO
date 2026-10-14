<x-admin-layout title="Produk">

    @php
        $imgSrc = function ($p) {
            $path = $p->image_url ?? $p->image_path;
            return filter_var($path, FILTER_VALIDATE_URL) ? $path : asset('storage/' . $path);
        };
        $failedId = (int) old('product_id');
    @endphp

    <div>
        <h1 class="text-lg sm:text-xl font-bold text-slate-900">Produk</h1>
        <p class="text-sm text-slate-500 mt-0.5">Turunkan produk yang melanggar aturan. Produk yang diturunkan hilang dari katalog dan tidak bisa dibeli; penjual tetap melihatnya di Toko Saya beserta alasannya.</p>
    </div>

    <!-- Tab status -->
    <nav class="flex overflow-x-auto no-scrollbar border-b border-slate-200" aria-label="Filter status produk">
        @foreach($statuses as $key => $label)
            <a href="{{ route('admin.products', array_filter(['status' => $key === 'semua' ? null : $key, 'q' => $search ?: null])) }}"
               class="shrink-0 px-4 py-2.5 text-sm font-semibold border-b-2 -mb-px {{ $status === $key ? 'border-emerald-600 text-emerald-700' : 'border-transparent text-slate-500 hover:text-slate-700' }}"
               @if($status === $key) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>

    <form method="GET" action="{{ route('admin.products') }}" class="flex gap-2" role="search">
        @if($status !== 'semua')
            <input type="hidden" name="status" value="{{ $status }}">
        @endif
        <label for="q" class="sr-only">Cari produk atau penjual</label>
        <input type="search" id="q" name="q" value="{{ $search }}" placeholder="Cari nama barang atau penjual"
               class="flex-1 rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
        <button type="submit" class="h-[38px] px-4 rounded-lg bg-slate-900 hover:bg-slate-700 text-white text-sm font-semibold">Cari</button>
    </form>

    <section class="space-y-3" aria-label="Daftar produk">
        @forelse($products as $product)
            @php
                $takenDown = $product->isTakenDown();
                [$statusText, $statusClass] = $takenDown
                    ? ['Diturunkan', 'bg-rose-100 text-rose-800']
                    : match (strtolower((string) $product->status)) {
                        'available' => ['Tersedia', 'bg-emerald-100 text-emerald-800'],
                        'booked'    => ['Di-booking', 'bg-amber-100 text-amber-800'],
                        default     => ['Terjual', 'bg-slate-200 text-slate-700'],
                    };
                $seller = $product->user;
            @endphp
            <article class="rounded-xl bg-white border {{ $takenDown ? 'border-rose-200' : 'border-slate-200' }} p-3 sm:p-4"
                     x-data="{ open: {{ $failedId === $product->id ? 'true' : 'false' }} }">
                <div class="flex gap-3">
                    <a href="{{ route('product.show', $product) }}" target="_blank" rel="noopener" class="shrink-0 w-16 h-16 sm:w-20 sm:h-20 rounded-lg overflow-hidden bg-slate-100 border border-slate-200">
                        <img src="{{ $imgSrc($product) }}" alt="{{ $product->title }}" loading="lazy" class="w-full h-full object-cover {{ $takenDown ? 'grayscale' : '' }}"
                             onerror="this.onerror=null; this.style.visibility='hidden'">
                    </a>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full {{ $statusClass }}">{{ $statusText }}</span>
                            <span class="text-[11px] text-slate-500">{{ $categories[$product->kategori]['label'] ?? ($product->kategori ?: 'Tanpa kategori') }}</span>
                        </div>
                        <h2 class="mt-1 font-semibold text-slate-900 leading-snug line-clamp-2">
                            <a href="{{ route('product.show', $product) }}" target="_blank" rel="noopener" class="hover:underline">{{ $product->title }}</a>
                        </h2>
                        <p class="text-sm text-slate-600">
                            <span class="font-bold text-slate-900 tabular-nums">Rp{{ number_format($product->price, 0, ',', '.') }}</span>
                            · {{ $seller ? ($seller->nama_toko ?: $seller->name) : 'Penjual dihapus' }}
                        </p>
                        @if($takenDown)
                            <p class="mt-1.5 rounded-md bg-rose-50 border border-rose-200 px-2.5 py-1.5 text-xs text-rose-800">
                                <span class="font-semibold">Alasan:</span> {{ $product->takedown_reason }}
                                <span class="text-rose-600">· {{ $product->taken_down_at->locale('id')->translatedFormat('d M Y') }}</span>
                            </p>
                        @endif
                    </div>
                    <div class="shrink-0 self-start">
                        @if($takenDown)
                            <form action="{{ route('admin.products.restore', $product) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="h-9 px-3 rounded-lg border border-emerald-600 text-emerald-700 hover:bg-emerald-50 text-sm font-semibold">Tayangkan</button>
                            </form>
                        @else
                            <button type="button" @click="open = !open" :aria-expanded="open.toString()" aria-controls="takedown-{{ $product->id }}"
                                    class="h-9 px-3 rounded-lg border border-rose-300 text-rose-700 hover:bg-rose-50 text-sm font-semibold">Turunkan</button>
                        @endif
                    </div>
                </div>

                @unless($takenDown)
                    <form id="takedown-{{ $product->id }}" x-show="open" x-cloak action="{{ route('admin.products.takeDown', $product) }}" method="POST"
                          class="mt-3 pt-3 border-t border-slate-100 space-y-2" @if($failedId !== $product->id) style="display: none" @endif>
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="product_id" value="{{ $product->id }}">
                        <label for="reason-{{ $product->id }}" class="block text-sm font-semibold text-slate-700">Alasan penurunan (terlihat oleh penjual)</label>
                        <textarea id="reason-{{ $product->id }}" name="reason" rows="2" maxlength="500" required
                                  placeholder="Mis. Foto tidak sesuai barang asli / barang terlarang / deskripsi menyesatkan"
                                  class="w-full rounded-lg border-slate-300 text-sm focus:border-rose-500 focus:ring-rose-500">{{ $failedId === $product->id ? old('reason') : '' }}</textarea>
                        @if($failedId === $product->id)
                            @error('reason', 'takedown')
                                <p class="text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        @endif
                        <div class="flex justify-end gap-2">
                            <button type="button" @click="open = false" class="h-9 px-3 rounded-lg text-slate-600 hover:bg-slate-100 text-sm font-semibold">Batal</button>
                            <button type="submit" class="h-9 px-4 rounded-lg bg-rose-600 hover:bg-rose-700 text-white text-sm font-semibold">Turunkan produk</button>
                        </div>
                    </form>
                @endunless
            </article>
        @empty
            <div class="text-center py-14 rounded-xl border border-dashed border-slate-300 bg-white">
                <p class="text-sm text-slate-600">Tidak ada produk yang cocok dengan filter.</p>
            </div>
        @endforelse

        @if($products->hasPages())
            <div>{{ $products->links() }}</div>
        @endif
    </section>
</x-admin-layout>

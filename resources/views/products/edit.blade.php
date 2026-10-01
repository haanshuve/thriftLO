<x-market-layout title="Edit Barang">

    <main class="max-w-xl mx-auto px-4 sm:px-6 py-4 sm:py-6 space-y-4">

        <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1 text-sm font-medium text-slate-500 hover:text-slate-700">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
            Kembali ke Toko Saya
        </a>

        <h1 class="text-lg sm:text-xl font-bold text-slate-900">Edit barang</h1>

        <form action="{{ route('product.update', $product->id) }}" method="POST" enctype="multipart/form-data" class="rounded-xl border border-slate-200 p-4 sm:p-5 space-y-3.5">
            @csrf
            @method('PUT')

            @if($errors->product->any())
                <div class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2.5 text-sm text-rose-700" role="alert">
                    <p class="font-semibold">Perubahan belum tersimpan:</p>
                    <ul class="list-disc list-inside mt-0.5">
                        @foreach($errors->product->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @include('products.partials.form-fields', ['product' => $product, 'sellerInBatam' => $sellerInBatam])

            <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2.5 rounded-lg text-sm">
                Simpan perubahan
            </button>
        </form>
    </main>
</x-market-layout>

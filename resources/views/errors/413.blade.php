{{-- Request melebihi post_max_size (biasanya Video Proof terlalu besar). Session belum jalan di titik ini, jadi halaman ini berdiri sendiri tanpa layout. --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File terlalu besar - thriftLO</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-white text-slate-800 font-sans antialiased">
    <main class="min-h-screen flex items-center justify-center px-4">
        <div class="max-w-md w-full rounded-2xl border border-rose-200 bg-rose-50 p-6 text-center" role="alert">
            <p class="text-4xl" aria-hidden="true">🎥</p>
            <h1 class="mt-2 text-lg font-bold text-rose-800">File yang diunggah terlalu besar</h1>
            <p class="mt-2 text-sm text-rose-700">
                Barang belum tersimpan. Ukuran Video Proof maksimal {{ config('thriftlo.video_proof.max_mb') }}MB
                dan foto produk maksimal 5MB. Rekam video lebih singkat (maks. 60 detik) atau kecilkan resolusinya, lalu coba lagi.
            </p>
            <a href="javascript:history.back()" class="mt-4 inline-block rounded-lg bg-emerald-600 hover:bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Kembali ke form</a>
        </div>
    </main>
</body>
</html>

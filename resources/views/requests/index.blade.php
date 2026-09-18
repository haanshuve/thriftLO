<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>One to Buy - Request Barang thriftLO</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 text-slate-800 font-sans antialiased pb-20">

    <!-- Navbar Sederhana -->
    <header class="bg-gradient-to-r from-emerald-600 via-teal-600 to-emerald-700 text-white shadow-md sticky top-0 z-50">
        <div class="container mx-auto px-4 sm:px-8 py-3 flex justify-between items-center">
            <a href="{{ route('home') }}" class="font-black text-lg tracking-tight flex items-center gap-2">
                🛍️ thriftLO <span class="text-[10px] bg-emerald-900 px-2 py-0.5 rounded-full uppercase">Batam Market</span>
            </a>
            <a href="{{ route('home') }}" class="bg-white/10 hover:bg-white/20 text-white text-xs font-extrabold px-3.5 py-2 rounded-xl transition">
                ← Kembali ke Katalog Utama
            </a>
        </div>
    </header>

    <main class="container mx-auto max-w-5xl px-4 py-8">
        <!-- Banner Info -->
        <div class="bg-gradient-to-br from-emerald-900 to-teal-900 text-white p-6 sm:p-8 rounded-3xl shadow-xl mb-8 flex flex-col sm:flex-row justify-between items-center gap-6">
            <div>
                <span class="bg-emerald-500/20 text-emerald-300 text-[10px] font-black px-3 py-1 rounded-full border border-emerald-400/30 uppercase tracking-wider mb-2 inline-block">
                    📢 Fitur Reverse Marketplace (One to Buy)
                </span>
                <h2 class="text-2xl sm:text-3xl font-black">Cari Barang Impianmu di Sini</h2>
                <p class="text-xs sm:text-sm text-slate-300 mt-1">Punya barang preloved incaran tapi belum ada di katalog? Posting permintaannya, biarkan para penjual Batam yang menawarkan barangnya ke kamu!</p>
            </div>
            @auth
                <button onclick="document.getElementById('requestModal').classList.remove('hidden')" class="bg-amber-400 hover:bg-amber-500 text-slate-950 text-xs font-black px-5 py-3 rounded-2xl shadow-lg transition shrink-0 uppercase tracking-wider">
                    ➕ Buat Request Barang
                </button>
            @else
                <a href="{{ route('login') }}" class="bg-amber-400 text-slate-950 text-xs font-black px-5 py-3 rounded-2xl shadow-lg uppercase tracking-wider">
                    Login untuk Posting Request
                </a>
            @endauth
        </div>

        @if(session('success'))
            <div class="bg-emerald-600 text-white p-4 rounded-2xl mb-6 shadow-md text-xs font-bold">
                🎉 {{ session('success') }}
            </div>
        @endif

        <!-- Daftar Request Feed -->
        <h3 class="font-black text-slate-900 text-lg mb-4">📋 Daftar Permintaan Pembeli di Batam</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @forelse($requests as $req)
                <div class="bg-white p-5 rounded-3xl shadow-sm border border-slate-200/80 flex flex-col justify-between hover:shadow-md transition">
                    <div>
                        <div class="flex justify-between items-center mb-2">
                            <span class="bg-emerald-50 text-emerald-700 text-[10px] font-black px-2.5 py-1 rounded-lg border border-emerald-100 uppercase">{{ $req->kategori }}</span>
                            <span class="text-[11px] font-bold text-slate-400">👤 {{ $req->user->name ?? 'Pembeli' }}</span>
                        </div>
                        <h4 class="font-black text-slate-900 text-base mb-1">{{ $req->nama_barang }}</h4>
                        <p class="text-xs text-slate-500 mb-4 line-clamp-2">{{ $req->deskripsi }}</p>
                    </div>

                    <div class="pt-4 border-t border-slate-100 flex justify-between items-center">
                        <div>
                            <span class="text-[10px] text-slate-400 font-bold block uppercase">Estimasi Budget:</span>
                            <span class="text-sm font-black text-emerald-700">Rp {{ number_format($req->budget_maksimal, 0, ',', '.') }}</span>
                        </div>
                        <div class="text-right">
                            <span class="text-[10px] text-slate-400 font-bold block uppercase">Lokasi COD:</span>
                            <span class="text-xs font-extrabold text-slate-700">📍 {{ $req->lokasi_cod }}</span>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full text-center py-16 bg-white rounded-3xl border border-dashed border-slate-300">
                    <span class="text-4xl block mb-2">📭</span>
                    <h4 class="font-bold text-slate-700 text-sm">Belum ada request barang</h4>
                    <p class="text-xs text-slate-400 mt-1">Jadilah yang pertama membuat permintaan barang preloved!</p>
                </div>
            @endforelse
        </div>
    </main>

    <!-- Modal Form Buat Request -->
    <div id="requestModal" class="fixed inset-0 bg-slate-900/70 backdrop-blur-sm hidden flex items-center justify-center z-50 p-4">
        <div class="bg-white p-6 sm:p-7 rounded-3xl max-w-md w-full shadow-2xl border border-slate-100">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-base font-black text-slate-900">📢 Posting Request Barang</h3>
                <button onclick="document.getElementById('requestModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
            </div>

            <form action="{{ route('requests.store') }}" method="POST" class="space-y-3.5">
                @csrf
                <div>
                    <label class="block text-xs font-extrabold text-slate-700 mb-1">Nama Barang yang Dicari</label>
                    <input type="text" name="nama_barang" placeholder="Contoh: Kamera Sony A6000 / Hoodie Vintage" required class="w-full border border-slate-200 rounded-2xl p-3 text-xs font-medium focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-xs font-extrabold text-slate-700 mb-1">Kategori</label>
                    <select name="kategori" class="w-full border border-slate-200 rounded-2xl p-3 text-xs font-bold text-slate-700 focus:ring-2 focus:ring-emerald-500">
                        <option value="Fashion">👕 Fashion / Pakaian</option>
                        <option value="Vintage Tech">📷 Vintage Tech (Gadget)</option>
                        <option value="Lainnya">📦 Lainnya</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-extrabold text-slate-700 mb-1">Budget Maksimal (Rp)</label>
                    <input type="number" name="budget_maksimal" placeholder="Contoh: 2500000" required class="w-full border border-slate-200 rounded-2xl p-3 text-xs font-medium focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-xs font-extrabold text-slate-700 mb-1">Preferensi Lokasi COD (Batam)</label>
                    <input type="text" name="lokasi_cod" placeholder="Contoh: Batam Center / Nagoya Hill" required class="w-full border border-slate-200 rounded-2xl p-3 text-xs font-medium focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-xs font-extrabold text-slate-700 mb-1">Deskripsi / Detail Tambahan</label>
                    <textarea name="deskripsi" rows="3" placeholder="Jelaskan kondisi minimal atau kriteria khusus..." required class="w-full border border-slate-200 rounded-2xl p-3 text-xs font-medium focus:ring-2 focus:ring-emerald-500"></textarea>
                </div>
                <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-black py-3 rounded-2xl transition text-xs shadow-lg uppercase tracking-wider mt-2">
                    Kirim Request ke Penjual
                </button>
            </form>
        </div>
    </div>
</body>
</html>

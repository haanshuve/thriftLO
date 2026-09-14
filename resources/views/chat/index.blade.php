<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pusat Obrolan - thriftLO Batam</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 text-slate-800 font-sans antialiased h-screen flex flex-col overflow-hidden">

    <!-- Header Navbar Modern -->
    <header class="bg-gradient-to-r from-emerald-600 via-teal-600 to-emerald-700 text-white px-6 py-3.5 flex justify-between items-center shadow-md z-20">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 bg-white/10 backdrop-blur-md rounded-2xl flex items-center justify-center text-lg shadow-inner border border-white/20">
                💬
            </div>
            <div>
                <h1 class="text-sm font-black tracking-tight">Pusat Obrolan <span class="text-[10px] bg-emerald-900/60 px-2 py-0.5 rounded font-mono">thriftLO</span></h1>
                <p class="text-[10px] text-emerald-100 font-medium">Diskusi produk, tawar harga, dan koordinasi COD Batam</p>
            </div>
        </div>
        <a href="{{ route('home') }}" class="bg-white/10 hover:bg-white/20 text-white text-xs font-bold px-4 py-2 rounded-2xl transition border border-white/20 backdrop-blur-sm flex items-center gap-1.5">
            ← Kembali ke Katalog
        </a>
    </header>

    <!-- Main Messenger Container -->
    <main class="flex-1 container mx-auto max-w-6xl p-4 sm:p-6 flex overflow-hidden">
        <div class="w-full bg-white rounded-3xl shadow-xl border border-slate-200/80 overflow-hidden flex flex-col md:flex-row">

            <!-- SIDEBAR: DAFTAR KONTAK OBROLAN -->
            <div class="w-full md:w-80 lg:w-96 border-r border-slate-100 bg-slate-50/50 flex flex-col h-full">
                <div class="p-4 border-b border-slate-100 bg-white">
                    <h3 class="text-xs font-black text-slate-800 uppercase tracking-wider">Daftar Percakapan</h3>
                    <p class="text-[10px] text-slate-400 mt-0.5">Pilih kontak untuk mulai berdiskusi</p>
                </div>

                <div class="flex-1 overflow-y-auto p-3 space-y-2">
                    @forelse($contacts as $c)
                        <a href="{{ route('chat.index', ['user_id' => $c->id]) }}" class="flex items-center gap-3 p-3 rounded-2xl transition group {{ isset($activeContact) && $activeContact->id == $c->id ? 'bg-emerald-600 text-white shadow-lg shadow-emerald-600/20' : 'bg-white hover:bg-slate-100/80 text-slate-700 border border-slate-200/60' }}">
                            <div class="w-11 h-11 rounded-2xl {{ isset($activeContact) && $activeContact->id == $c->id ? 'bg-white/20 text-white' : 'bg-emerald-100 text-emerald-800' }} flex items-center justify-center font-black text-sm transition">
                                {{ strtoupper(substr($c->name, 0, 1)) }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex justify-between items-baseline mb-0.5">
                                    <h4 class="text-xs font-black truncate {{ isset($activeContact) && $activeContact->id == $c->id ? 'text-white' : 'text-slate-900' }}">{{ $c->name }}</h4>
                                </div>
                                <p class="text-[10px] font-bold truncate {{ isset($activeContact) && $activeContact->id == $c->id ? 'text-emerald-100' : 'text-slate-400' }}">
                                    {{ $c->role == 'penjual' ? '🏬 Penjual Verified' : '🛍️ Pembeli Thrift' }}
                                </p>
                            </div>
                        </a>
                    @empty
                        <div class="text-center py-12 px-4 text-slate-400">
                            <span class="text-4xl block mb-2">📭</span>
                            <p class="text-xs font-bold">Belum ada daftar kontak obrolan.</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- AREA UTAMA PERCAKAPAN (CHAT ROOM) -->
            <div class="flex-1 flex flex-col h-full bg-white">
                @if(isset($activeContact) && $activeContact)
                    <!-- Header Kontak Aktif -->
                    <div class="p-4 border-b border-slate-100 flex items-center justify-between bg-white shadow-sm z-10">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-emerald-600 to-teal-700 text-white flex items-center justify-center font-black text-xs shadow-md">
                                {{ strtoupper(substr($activeContact->name, 0, 1)) }}
                            </div>
                            <div>
                                <h3 class="text-xs font-black text-slate-900">{{ $activeContact->name }}</h3>
                                <span class="text-[10px] text-emerald-600 font-extrabold flex items-center gap-1 mt-0.5">
                                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span> Lapak Active (Batam)
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Lampiran Konteks Produk (Jika Ada) -->
                    @if(isset($selectedProduct) && $selectedProduct)
                        <div class="px-4 py-2.5 bg-emerald-50/80 border-b border-emerald-100 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <img src="{{ $selectedProduct->image_url }}" class="w-10 h-10 rounded-xl object-cover shadow-sm border border-emerald-200">
                                <div>
                                    <span class="text-[9px] bg-emerald-200 text-emerald-900 font-black px-2 py-0.5 rounded uppercase">Membahas Produk</span>
                                    <h4 class="text-xs font-black text-slate-900 mt-0.5">{{ $selectedProduct->nama_barang }}</h4>
                                </div>
                            </div>
                            <span class="text-xs font-black text-emerald-700">Rp {{ number_format($selectedProduct->harga, 0, ',', '.') }}</span>
                        </div>
                    @endif

                    <!-- Kotak Riwayat Pesan (Chat Bubbles) -->
                    <div class="flex-1 p-4 sm:p-6 overflow-y-auto space-y-3 bg-slate-50/40">
                        @forelse($messages as $m)
                            <div class="flex flex-col {{ $m->sender_id == Auth::id() ? 'items-end' : 'items-start' }}">
                                <div class="max-w-xs sm:max-w-md p-3.5 rounded-2xl text-xs font-medium shadow-sm leading-relaxed {{ $m->sender_id == Auth::id() ? 'bg-emerald-600 text-white rounded-br-none shadow-emerald-600/10' : 'bg-white text-slate-800 border border-slate-200/80 rounded-bl-none' }}">
                                    {{ $m->message }}
                                </div>
                                <span class="text-[9px] text-slate-400 mt-1 px-1 font-bold">
                                    {{ $m->created_at->format('H:i') }}
                                </span>
                            </div>
                        @empty
                            <div class="text-center py-16 text-slate-400">
                                <span class="text-5xl block mb-2">💬</span>
                                <p class="text-xs font-bold text-slate-600">Belum ada riwayat pesan dengan {{ $activeContact->name }}.</p>
                                <p class="text-[11px] text-slate-400 mt-1">Kirim pesan untuk menanyakan ketersediaan stok atau janjian COD di Batam!</p>
                            </div>
                        @endforelse
                    </div>

                    <!-- Form Input Pesan (Kirim) -->
                    <form action="{{ route('chat.send') }}" method="POST" class="p-4 border-t border-slate-100 bg-white flex gap-3 items-center">
                        @csrf
                        <input type="hidden" name="receiver_id" value="{{ $activeContact->id }}">
                        @if(isset($selectedProduct) && $selectedProduct)
                            <input type="hidden" name="product_id" value="{{ $selectedProduct->id }}">
                        @endif

                        <input type="text" name="message" required placeholder="Tulis pesan diskusimu di sini..." class="flex-1 bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-xs font-medium focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition">

                        <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white px-6 py-3 rounded-2xl text-xs font-black uppercase tracking-wider transition shadow-lg shadow-emerald-600/25">
                            Kirim 🚀
                        </button>
                    </form>
                @else
                    <!-- Tampilan Kosong Jika Belum Pilih Kontak -->
                    <div class="flex-1 flex flex-col items-center justify-center text-center p-8 text-slate-400 bg-slate-50/20">
                        <div class="w-16 h-16 bg-emerald-50 rounded-3xl flex items-center justify-center text-3xl mb-3 shadow-inner border border-emerald-100">
                            💬
                        </div>
                        <h3 class="text-sm font-black text-slate-800">Pusat Diskusi thriftLO</h3>
                        <p class="text-xs text-slate-400 mt-1 max-w-sm">Silakan pilih salah satu kontak obrolan di sebelah kiri untuk mulai bernegosiasi atau mengatur jadwal COD.</p>
                    </div>
                @endif
            </div>

        </div>
    </main>

</body>
</html>

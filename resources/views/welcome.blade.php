<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>thriftLO - Smart Preloved & Thrifting Hub Batam</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 text-slate-800 font-sans antialiased pb-20 selection:bg-emerald-500 selection:text-white">

    <!-- Header / Navbar Navigasi Modern -->
    <header class="bg-gradient-to-r from-emerald-600 via-teal-600 to-emerald-700 text-white shadow-md sticky top-0 z-50 backdrop-blur-md">
        <div class="container mx-auto px-4 sm:px-8 py-3.5 flex justify-between items-center">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-white/10 backdrop-blur-md rounded-2xl flex items-center justify-center text-xl shadow-inner border border-white/20">
                    🌱
                </div>
                <div>
                    <h1 class="text-xl sm:text-2xl font-black tracking-tight flex items-center gap-2">
                        thriftLO
                        <span class="text-[10px] bg-emerald-900/60 text-emerald-200 border border-emerald-400/30 px-2.5 py-0.5 rounded-full font-bold uppercase tracking-widest">Batam</span>
                    </h1>
                    <p class="text-[10px] text-emerald-100 hidden sm:block font-medium">Circular Preloved & Thrift Marketplace</p>
                </div>
            </div>

            <nav class="flex items-center gap-3 text-xs font-extrabold">
                <a href="#katalog" class="text-emerald-100 hover:text-white px-3 py-2 rounded-xl transition hover:bg-white/10 hidden md:block">
                    Katalog Utama
                </a>

                @auth
                    <!-- Tampilan Jika User Sudah Login -->
                    <a href="{{ route('chat.index') }}" class="bg-white/10 hover:bg-white/20 text-white border border-white/20 px-4 py-2 rounded-2xl transition flex items-center gap-1.5 backdrop-blur-sm">
                        💬 Chat
                    </a>

                    @if(Auth::user()->role === 'pembeli')
                        <a href="{{ route('bookings.index') }}" class="bg-white/10 hover:bg-white/20 text-white border border-white/20 px-4 py-2 rounded-2xl transition flex items-center gap-1.5 backdrop-blur-sm">
                            🎟️ Tiket Saya
                        </a>
                    @endif

                    @if(Auth::user()->role === 'penjual')
                        <a href="{{ route('dashboard') }}" class="bg-amber-400 hover:bg-amber-500 text-gray-900 px-4 py-2 rounded-2xl transition flex items-center gap-1.5 shadow-md font-black">
                            🏬 Dashboard Penjual
                        </a>
                    @endif

                    <form method="POST" action="{{ route('logout') }}" class="inline">
                        @csrf
                        <button type="submit" class="bg-rose-500/80 hover:bg-rose-600 text-white px-3 py-2 rounded-2xl transition shadow-sm">
                            🚪 Keluar
                        </button>
                    </form>
                @else
                    <!-- Tampilan Jika Pengunjung / Pembeli Belum Login -->
                    <a href="{{ route('login') }}" class="bg-white/10 hover:bg-white/20 text-white border border-white/20 px-4 py-2.5 rounded-2xl transition flex items-center gap-1.5 backdrop-blur-sm">
                        🔑 Masuk / Login
                    </a>
                    <a href="{{ route('register') }}" class="bg-amber-400 hover:bg-amber-500 text-gray-900 px-4 py-2.5 rounded-2xl transition flex items-center gap-1.5 shadow-md font-black">
                        ✍️ Daftar Akun
                    </a>
                @endauth
            </nav>
        </div>
    </header>

    <!-- Hero Section Banner (SDGs 12 & 8 Impact Counter) -->
    <section class="bg-gradient-to-br from-emerald-950 via-teal-900 to-slate-900 text-white py-12 px-4 border-b border-emerald-800/40 relative overflow-hidden">
        <div class="absolute -right-12 -top-12 w-96 h-96 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="container mx-auto max-w-6xl flex flex-col md:flex-row justify-between items-center gap-8 relative z-10">
            <div class="max-w-xl text-center md:text-left">
                <span class="inline-flex items-center gap-1.5 bg-emerald-500/20 text-emerald-300 text-[11px] font-black px-3.5 py-1 rounded-full border border-emerald-500/30 uppercase tracking-wider mb-3">
                    ✨ Batam Circular Commerce Project
                </span>
                <h2 class="text-3xl sm:text-4xl font-black tracking-tight leading-tight">
                    Preloved Berkualitas, <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-400 to-amber-300">Gaya Tanpa Limbah.</span>
                </h2>
                <p class="text-xs sm:text-sm text-slate-300 mt-2 font-normal leading-relaxed">
                    Platform thrifting O2O khusus Kota Batam. Beli barang preloved verified, tawar harga interaktif, dan kunci barang via Token QR COD.
                </p>
            </div>

            <!-- Card Impact Counter -->
            <div class="grid grid-cols-2 gap-4 w-full md:w-auto">
                <div class="bg-white/5 border border-white/10 backdrop-blur-xl p-4 sm:p-5 rounded-3xl text-center shadow-2xl min-w-[150px]">
                    <span class="text-3xl sm:text-4xl font-black text-amber-300 block tracking-tight">
                        {{ number_format($wastePreventedKg ?? 0, 1) }} <span class="text-xs font-bold text-emerald-200">kg</span>
                    </span>
                    <span class="text-[10px] text-slate-300 font-extrabold uppercase tracking-wider block mt-1">Limbah Dicegah</span>
                </div>
                <div class="bg-white/5 border border-white/10 backdrop-blur-xl p-4 sm:p-5 rounded-3xl text-center shadow-2xl min-w-[150px]">
                    <span class="text-3xl sm:text-4xl font-black text-emerald-400 block tracking-tight">
                        {{ $totalBookings ?? 0 }}
                    </span>
                    <span class="text-[10px] text-slate-300 font-extrabold uppercase tracking-wider block mt-1">Transaksi COD QR</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Main Container Catalog -->
    <main class="container mx-auto max-w-6xl px-4 py-10">

        <!-- Notifikasi Session Alert -->
        @if(session('success'))
            <div class="bg-emerald-600 text-white p-4 rounded-3xl mb-8 shadow-xl border border-emerald-400/30 flex flex-col sm:flex-row justify-between items-center gap-3">
                <div class="flex items-center gap-3">
                    <span class="text-2xl">🎉</span>
                    <div>
                        <h4 class="font-extrabold text-sm">Berhasil!</h4>
                        <p class="text-xs text-emerald-100">{{ session('success') }}</p>
                    </div>
                </div>
            </div>
        @endif

        <!-- Filter Bar Catalog Modern -->
        <div class="bg-white p-5 rounded-3xl shadow-sm border border-slate-200/80 mb-8 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h3 class="font-black text-slate-900 text-lg flex items-center gap-2">
                    🛍️ Katalog Barang Preloved
                </h3>
                <p class="text-xs text-slate-400 font-medium">Jelajahi koleksi barang bekas berkualitas di Batam</p>
            </div>

            <div class="w-full sm:w-auto flex items-center gap-2">
                <form action="{{ route('home') }}" method="GET" class="w-full sm:w-auto">
                    <select name="kategori" onchange="this.form.submit()" class="w-full sm:w-auto bg-slate-50 border border-slate-200 rounded-2xl px-4 py-2.5 text-xs font-extrabold text-slate-700 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition shadow-sm">
                        <option value="all">🔍 Semua Kategori</option>
                        <option value="Fashion" {{ request('kategori') == 'Fashion' ? 'selected' : '' }}>👕 Fashion / Pakaian</option>
                        <option value="Vintage Tech" {{ request('kategori') == 'Vintage Tech' ? 'selected' : '' }}>📷 Vintage Tech (Gadget)</option>
                    </select>
                </form>
            </div>
        </div>

        <!-- Catalog Product Grid Dynamic -->
        <section id="katalog" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 mb-16">
            @forelse($products as $p)
                <div class="bg-white rounded-3xl shadow-sm border border-slate-200/80 overflow-hidden hover:-translate-y-2 transition-all duration-300 hover:shadow-2xl flex flex-col justify-between group">
                    <div>
                        <!-- Foto Produk (Mendukung File Storage Lokal & URL Eksternal) -->
                        <div class="relative h-56 bg-slate-100 overflow-hidden">
                            <img src="{{ filter_var($p->image_url, FILTER_VALIDATE_URL) ? $p->image_url : asset('storage/' . $p->image_url) }}"
                                 alt="{{ $p->nama_barang ?? $p->title }}"
                                 class="w-full h-full object-cover group-hover:scale-105 transition duration-500">

                            <span class="absolute top-3 left-3 {{ $p->mode_jual == 'borongan' ? 'bg-amber-500 text-amber-950' : 'bg-blue-600 text-white' }} text-[10px] font-black px-2.5 py-1 rounded-xl uppercase tracking-wider shadow-md">
                                {{ $p->mode_jual == 'borongan' ? '📦 BORONGAN' : '🛍️ ECERAN' }}
                            </span>

                            <span class="absolute top-3 right-3 bg-slate-900/80 backdrop-blur-md text-white text-[10px] font-extrabold px-2.5 py-1 rounded-xl border border-white/20 shadow-sm">
                                {{ $p->grade }}
                            </span>
                        </div>

                        <!-- Content Card -->
                        <div class="p-5">
                            <div class="flex justify-between items-center text-xs text-slate-400 mb-1 font-bold">
                                <span class="text-emerald-600 font-extrabold uppercase tracking-wide text-[10px] bg-emerald-50 px-2 py-0.5 rounded-md border border-emerald-100">{{ $p->kategori }}</span>
                                <span class="text-[11px] truncate max-w-[110px] text-slate-500">👤 {{ $p->user->name ?? 'Penjual Batam' }}</span>
                            </div>

                            <h4 class="font-black text-slate-900 text-base line-clamp-1 mb-1 group-hover:text-emerald-600 transition">
                                {{ $p->nama_barang ?? $p->title }}
                            </h4>

                            <p class="text-xs text-slate-500 line-clamp-2 mb-3 font-normal leading-relaxed">
                                {{ $p->description ?? $p->deskripsi ?? 'Kondisi fisik telah terverifikasi.' }}
                            </p>

                            <!-- Video Proof Tag -->
                            @if($p->video_proof_url ?? $p->video_proof)
                                <a href="{{ $p->video_proof_url ?? $p->video_proof }}" target="_blank" class="inline-flex items-center gap-1.5 text-[11px] font-extrabold text-blue-600 hover:text-blue-800 bg-blue-50 px-3 py-1 rounded-xl border border-blue-100 transition">
                                    🎥 Video Proof Disertakan
                                </a>
                            @else
                                <span class="inline-flex items-center gap-1 text-[10px] font-extrabold text-emerald-800 bg-emerald-50 px-2.5 py-1 rounded-xl border border-emerald-100">
                                    ✓ Fisik Verified
                                </span>
                            @endif
                        </div>
                    </div>

                    <!-- Card Footer Action Buttons -->
                    <div class="p-5 pt-0">
                        <div class="pt-3 border-t border-slate-100">
                            <div class="flex justify-between items-baseline mb-3">
                                <span class="text-[11px] text-slate-400 font-extrabold uppercase tracking-wider">Harga:</span>
                                <span class="text-xl font-black text-emerald-700">Rp {{ number_format($p->harga ?? $p->price, 0, ',', '.') }}</span>
                            </div>

                            <div class="grid grid-cols-2 gap-2">
                                <button onclick="openNegoModal('{{ $p->nama_barang ?? $p->title }}', '{{ $p->harga ?? $p->price }}')" class="w-full bg-amber-50 hover:bg-amber-100 text-amber-900 text-xs font-black py-2.5 rounded-2xl border border-amber-200/80 transition shadow-sm">
                                    💬 Tawar
                                </button>

                                @if($p->status == 'Available')
                                    <button onclick="openBookingModal('{{ $p->id }}', '{{ $p->nama_barang ?? $p->title }}')" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-black py-2.5 rounded-2xl transition shadow-md hover:shadow-emerald-200">
                                        ⚡ Booking
                                    </button>
                                @else
                                    <button disabled class="w-full bg-slate-100 text-slate-400 text-xs font-bold py-2.5 rounded-2xl cursor-not-allowed border border-slate-200">
                                        🔒 Booked
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full text-center py-20 bg-white rounded-3xl border border-dashed border-slate-300 shadow-sm">
                    <span class="text-6xl block mb-3">🛍️</span>
                    <h4 class="font-extrabold text-slate-800 text-lg">Katalog Masih Kosong</h4>
                    <p class="text-xs text-slate-400 mt-1">Belum ada barang preloved yang ditayangkan saat ini.</p>
                </div>
            @endforelse
        </section>

    </main>

    <!-- Modal Form Booking COD -->
    <div id="bookingModal" class="fixed inset-0 bg-slate-900/70 backdrop-blur-sm hidden flex items-center justify-center z-50 p-4">
        <div class="bg-white p-6 sm:p-7 rounded-3xl max-w-md w-full shadow-2xl border border-slate-100">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-base font-black text-slate-900">⚡ Atur Janji COD & Kunci Barang</h3>
                <button onclick="document.getElementById('bookingModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 text-2xl font-bold">&times;</button>
            </div>

            <form id="bookingForm" method="POST" action="" class="space-y-3.5">
                @csrf
                <div>
                    <label class="block text-xs font-extrabold text-slate-700 mb-1">Nama Barang</label>
                    <input type="text" id="modalProductName" disabled class="w-full bg-slate-100 border border-slate-200 rounded-2xl p-3 text-xs font-extrabold text-emerald-800">
                </div>
                <div>
                    <label class="block text-xs font-extrabold text-slate-700 mb-1">Lokasi Pertemuan COD (Batam)</label>
                    <input type="text" name="lokasi_cod" placeholder="Contoh: Batam Sunday Market / Mega Mall Batam Centre" required class="w-full border border-slate-200 rounded-2xl p-3 text-xs font-medium focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-xs font-extrabold text-slate-700 mb-1">Jadwal & Waktu COD</label>
                    <input type="datetime-local" name="waktu_cod" required class="w-full border border-slate-200 rounded-2xl p-3 text-xs font-medium focus:ring-2 focus:ring-emerald-500">
                </div>
                <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-black py-3 rounded-2xl transition text-xs shadow-lg uppercase tracking-wider mt-2">
                    Dapatkan Token QR & Kunci Barang
                </button>
            </form>
        </div>
    </div>

    <!-- Modal Tawar Harga -->
    <div id="negoModal" class="fixed inset-0 bg-slate-900/70 backdrop-blur-sm hidden flex items-center justify-center z-50 p-4">
        <div class="bg-white p-6 rounded-3xl max-w-sm w-full shadow-2xl text-center border border-slate-100">
            <h3 class="text-base font-black text-slate-900 mb-1">💬 Fitur Tawar Harga</h3>
            <p id="negoItemName" class="text-xs text-emerald-700 font-extrabold mb-4"></p>

            <div class="mb-3 text-left">
                <label class="block text-xs font-extrabold text-slate-700 mb-1">Harga Pasang:</label>
                <input type="text" id="negoOriginalPrice" disabled class="w-full bg-slate-100 border border-slate-200 rounded-2xl p-2.5 text-xs font-black text-slate-700">
            </div>
            <div class="mb-5 text-left">
                <label class="block text-xs font-extrabold text-slate-700 mb-1">Ajukan Nominal Tawaranmu (Rp):</label>
                <input type="number" id="negoInputPrice" placeholder="Nominal tawaran..." class="w-full border border-slate-200 rounded-2xl p-3 text-xs font-bold focus:ring-2 focus:ring-amber-500">
            </div>

            <div class="flex gap-2">
                <button onclick="document.getElementById('negoModal').classList.add('hidden')" class="w-1/2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-black py-3 rounded-2xl transition">
                    Batal
                </button>
                <button onclick="submitNego()" class="w-1/2 bg-amber-500 hover:bg-amber-600 text-gray-950 text-xs font-black py-3 rounded-2xl transition shadow-md">
                    Kirim Tawaran
                </button>
            </div>
        </div>
    </div>

    <script>
        function openBookingModal(productId, productName) {
            document.getElementById('modalProductName').value = productName;
            document.getElementById('bookingForm').action = "/product/" + productId + "/book";
            document.getElementById('bookingModal').classList.remove('hidden');
        }

        function openNegoModal(productName, originalPrice) {
            document.getElementById('negoItemName').innerText = productName;
            document.getElementById('negoOriginalPrice').value = "Rp " + new Intl.NumberFormat('id-ID').format(originalPrice);
            document.getElementById('negoModal').classList.remove('hidden');
        }

        function submitNego() {
            const tawaran = document.getElementById('negoInputPrice').value;
            if(!tawaran) {
                alert('Silakan masukkan nominal tawaranmu!');
                return;
            }
            alert('Tawaran sebesar Rp ' + new Intl.NumberFormat('id-ID').format(tawaran) + ' telah dikirimkan ke Penjual!');
            document.getElementById('negoModal').classList.add('hidden');
        }
    </script>

    <!-- Floating Chat Component -->
    @include('components.floating-chat')
</body>
</html>

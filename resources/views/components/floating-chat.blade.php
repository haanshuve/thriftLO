<!-- FLOATING CHAT WIDGET (Nyempil di Pojok Kanan Bawah) -->
<div class="fixed bottom-5 right-5 z-50 font-sans">

    <!-- Tombol Trigger Mengambang (Bulat Gelembung) -->
    <button onclick="toggleChatWidget()" id="chatToggleBtn" class="bg-gradient-to-r from-emerald-600 to-teal-600 text-white w-14 h-14 rounded-full shadow-2xl flex items-center justify-center text-2xl hover:scale-110 active:scale-95 transition-all duration-300 border-2 border-white group relative cursor-pointer">
        💬
        <!-- Badge Indikator Status Active -->
        <span class="absolute -top-1 -right-1 bg-amber-400 border-2 border-white text-gray-950 text-[9px] font-black w-5 h-5 rounded-full flex items-center justify-center shadow-md animate-bounce">
            !
        </span>
    </button>

    <!-- Kotak Jendela Chat Pop-up (Hidden secara Default) -->
    <div id="chatWidgetBox" class="hidden absolute bottom-20 right-0 w-80 sm:w-96 bg-white rounded-3xl shadow-2xl border border-slate-200/90 overflow-hidden flex flex-col h-[480px] transition-all transform scale-95 opacity-0 duration-300 backdrop-blur-md">

        <!-- Header Chat Box -->
        <div class="bg-gradient-to-r from-emerald-600 via-teal-600 to-emerald-700 text-white p-4 flex justify-between items-center shadow-sm">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-2xl bg-white/10 backdrop-blur-md border border-white/20 flex items-center justify-center text-sm shadow-inner">
                    💬
                </div>
                <div>
                    <h4 class="text-xs font-black tracking-wide flex items-center gap-1.5">
                        Pusat Obrolan <span class="text-[9px] bg-emerald-900/60 px-1.5 py-0.5 rounded font-mono">thriftLO</span>
                    </h4>
                    <p class="text-[9px] text-emerald-100 font-medium">Diskusi Cepat & Janji COD Batam</p>
                </div>
            </div>

            <div class="flex items-center gap-1.5">
                <!-- Tombol Perbesar ke Full Halaman Chat -->
                <a href="{{ route('chat.index') }}" title="Buka Halaman Chat Penuh" class="bg-white/10 hover:bg-white/20 text-white p-1.5 rounded-xl text-xs transition border border-white/20">
                    ↗
                </a>
                <!-- Tombol Tutup Widget -->
                <button onclick="toggleChatWidget()" class="bg-white/10 hover:bg-white/20 text-white w-7 h-7 rounded-xl flex items-center justify-center text-sm font-bold transition border border-white/20">
                    &times;
                </button>
            </div>
        </div>

        <!-- Isi Konten Jendela Chat (Ringkasan Kontak Cepat) -->
        <div class="flex-1 overflow-y-auto p-3 space-y-2 bg-slate-50/60">
            <div class="flex justify-between items-center px-1 mb-1">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-wider">Kontak Obrolan Aktif</p>
                <span class="text-[9px] font-extrabold text-emerald-600">● Real-time</span>
            </div>

            @auth
                @php
                    // Mengambil data user langsung menggunakan Fully Qualified Class Name (FQCN) tanpa keyword "use"
                    $widgetContacts = \App\Models\User::where('id', '!=', Auth::id())->take(6)->get();
                @endphp

                @forelse($widgetContacts as $wc)
                    <a href="{{ route('chat.index', ['user_id' => $wc->id]) }}" class="flex items-center gap-3 p-2.5 bg-white hover:bg-emerald-50/80 rounded-2xl border border-slate-200/60 transition shadow-sm group">
                        <div class="w-9 h-9 rounded-xl bg-emerald-100 text-emerald-800 flex items-center justify-center font-black text-xs group-hover:bg-emerald-600 group-hover:text-white transition shadow-xs">
                            {{ strtoupper(substr($wc->name, 0, 1)) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <h5 class="text-xs font-black text-slate-800 truncate group-hover:text-emerald-700 transition">{{ $wc->name }}</h5>
                            <p class="text-[10px] text-slate-400 truncate">{{ $wc->role == 'penjual' ? '🏬 Penjual Verified' : '🛍️ Pembeli Thrift' }}</p>
                        </div>
                        <span class="text-[10px] text-emerald-600 font-extrabold px-2.5 py-1 bg-emerald-50 rounded-xl group-hover:bg-emerald-600 group-hover:text-white transition">
                            Chat 💬
                        </span>
                    </a>
                @empty
                    <div class="text-center py-12 text-slate-400 text-xs">
                        <span class="text-3xl block mb-2">📭</span>
                        Belum ada kontak obrolan tersedia.
                    </div>
                @endforelse
            @else
                <div class="text-center py-16 px-4">
                    <span class="text-4xl block mb-2">🔑</span>
                    <h5 class="text-xs font-black text-slate-800">Login Diperlukan</h5>
                    <p class="text-[10px] text-slate-400 mt-1 mb-4">Silakan masuk ke akunmu untuk mengobrol dengan Penjual/Pembeli.</p>
                    <a href="{{ route('login') }}" class="inline-block bg-emerald-600 text-white text-xs font-extrabold px-4 py-2 rounded-xl shadow-md">
                        Masuk Sekarang
                    </a>
                </div>
            @endauth
        </div>

        <!-- Footer Widget (Tombol Navigasi Full Chat) -->
        @auth
            <div class="p-3 bg-white border-t border-slate-100 text-center">
                <a href="{{ route('chat.index') }}" class="block w-full bg-slate-900 hover:bg-slate-800 text-white text-xs font-black py-2.5 rounded-2xl transition shadow-md">
                    Buka Pusat Obrolan Penuh 🚀
                </a>
            </div>
        @endauth
    </div>
</div>

<!-- JavaScript Interaktif Animasi Widget Chat -->
<script>
    function toggleChatWidget() {
        const box = document.getElementById('chatWidgetBox');
        if (box.classList.contains('hidden')) {
            box.classList.remove('hidden');
            setTimeout(() => {
                box.classList.remove('scale-95', 'opacity-0');
                box.classList.add('scale-100', 'opacity-100');
            }, 10);
        } else {
            box.classList.remove('scale-100', 'opacity-100');
            box.classList.add('scale-95', 'opacity-0');
            setTimeout(() => {
                box.classList.add('hidden');
            }, 300);
        }
    }
</script>

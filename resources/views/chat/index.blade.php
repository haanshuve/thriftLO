<x-market-layout title="Chat" :full-height="true" :mobile-bottom-nav="!$activeContact" :floating-chat="false">

    @php
        $me = Auth::id();
        $displayName = fn ($u) => $u->role === 'penjual' && $u->nama_toko ? $u->nama_toko : $u->name;
        $roleLabel = fn ($u) => match ($u->role) {
            'penjual' => 'Penjual' . ($u->lokasi_lapak ? ' · ' . $u->lokasi_lapak : ''),
            'admin'   => 'Admin thriftLO',
            default   => 'Pembeli',
        };
        $shortTime = function ($date) {
            if ($date->isToday()) return $date->format('H:i');
            if ($date->isYesterday()) return 'Kemarin';
            return $date->format('d/m/y');
        };
        $dayLabel = function ($date) {
            if ($date->isToday()) return 'Hari ini';
            if ($date->isYesterday()) return 'Kemarin';
            return $date->format('d M Y');
        };
        $imgSrc = fn ($p) => filter_var($p->image_url ?? $p->image_path, FILTER_VALIDATE_URL)
            ? ($p->image_url ?? $p->image_path)
            : asset('storage/' . ($p->image_url ?? $p->image_path));
    @endphp

    <main class="flex-1 min-h-0 w-full max-w-6xl mx-auto sm:px-6 sm:py-4 flex">
        <div class="flex-1 min-h-0 flex bg-white sm:border sm:border-slate-200 sm:rounded-xl overflow-hidden">

            <!-- Daftar percakapan -->
            <aside class="{{ $activeContact ? 'hidden md:flex' : 'flex' }} w-full md:w-80 lg:w-96 shrink-0 flex-col min-h-0 md:border-r border-slate-200" aria-label="Daftar percakapan">
                <div class="px-4 py-3 border-b border-slate-200">
                    <h1 class="text-lg font-bold text-slate-900">Chat</h1>
                </div>

                <ul class="flex-1 min-h-0 overflow-y-auto divide-y divide-slate-100">
                    @forelse($conversations as $conv)
                        @php
                            $partner = $conv['partner'];
                            $last = $conv['last'];
                            $isActive = $activeContact && $activeContact->id === $partner->id;
                        @endphp
                        <li>
                            <a href="{{ route('chat.index', ['user_id' => $partner->id]) }}"
                               class="flex items-center gap-3 px-4 py-3 transition {{ $isActive ? 'bg-emerald-50' : 'hover:bg-slate-50' }}"
                               @if($isActive) aria-current="page" @endif>
                                <span class="w-11 h-11 shrink-0 rounded-full {{ $partner->role === 'penjual' ? 'bg-emerald-600 text-white' : 'bg-emerald-100 text-emerald-800' }} font-bold flex items-center justify-center" aria-hidden="true">
                                    {{ strtoupper(substr($displayName($partner), 0, 1)) }}
                                </span>
                                <span class="flex-1 min-w-0">
                                    <span class="flex items-baseline justify-between gap-2">
                                        <span class="text-sm font-semibold text-slate-900 truncate">{{ $displayName($partner) }}</span>
                                        @if($last)
                                            <span class="shrink-0 text-[11px] {{ $conv['unread'] ? 'text-emerald-700 font-semibold' : 'text-slate-400' }}">{{ $shortTime($last->created_at) }}</span>
                                        @endif
                                    </span>
                                    <span class="flex items-center justify-between gap-2 mt-0.5">
                                        <span class="text-xs truncate {{ $conv['unread'] ? 'text-slate-800 font-medium' : 'text-slate-500' }}">
                                            @if($last)
                                                {{ (int) $last->sender_id === $me ? 'Kamu: ' : '' }}{{ $last->message }}
                                            @else
                                                <span class="italic">Percakapan baru</span>
                                            @endif
                                        </span>
                                        @if($conv['unread'] > 0)
                                            <span class="shrink-0 min-w-[20px] h-5 px-1.5 rounded-full bg-emerald-600 text-white text-[11px] font-bold flex items-center justify-center" aria-label="{{ $conv['unread'] }} pesan belum dibaca">{{ $conv['unread'] }}</span>
                                        @endif
                                    </span>
                                </span>
                            </a>
                        </li>
                    @empty
                        <li class="px-6 py-14 text-center">
                            <p class="text-4xl mb-2" aria-hidden="true">💬</p>
                            <p class="font-bold text-slate-800">Belum ada percakapan</p>
                            <p class="text-sm text-slate-500 mt-1">Tekan tombol chat di kartu barang untuk bertanya atau menawar ke penjual.</p>
                            <a href="{{ route('home') }}" class="inline-block mt-4 px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold">Lihat katalog</a>
                        </li>
                    @endforelse
                </ul>
            </aside>

            <!-- Ruang percakapan -->
            <section class="{{ $activeContact ? 'flex' : 'hidden md:flex' }} flex-1 min-w-0 min-h-0 flex-col">
                @if($activeContact)
                    <!-- Kepala percakapan -->
                    <div class="flex items-center gap-3 px-3 sm:px-4 py-2.5 border-b border-slate-200">
                        <a href="{{ route('chat.index') }}" class="md:hidden w-9 h-9 -ml-1 rounded-lg flex items-center justify-center text-slate-600 hover:bg-slate-100" aria-label="Kembali ke daftar chat">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                        </a>
                        <span class="w-10 h-10 shrink-0 rounded-full {{ $activeContact->role === 'penjual' ? 'bg-emerald-600 text-white' : 'bg-emerald-100 text-emerald-800' }} font-bold flex items-center justify-center" aria-hidden="true">
                            {{ strtoupper(substr($displayName($activeContact), 0, 1)) }}
                        </span>
                        <div class="min-w-0">
                            <h2 class="text-sm font-semibold text-slate-900 truncate">{{ $displayName($activeContact) }}</h2>
                            <p class="text-xs text-slate-500 truncate">{{ $roleLabel($activeContact) }}</p>
                        </div>
                    </div>

                    <!-- Produk yang dibahas -->
                    @if($selectedProduct)
                        <div class="flex items-center gap-3 px-3 sm:px-4 py-2.5 border-b border-slate-200 bg-slate-50">
                            <img src="{{ $imgSrc($selectedProduct) }}" alt="" class="w-12 h-12 rounded-lg object-cover bg-slate-100 shrink-0"
                                 onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1523381210434-271e8be1f52b?auto=format&fit=crop&w=200&q=70'">
                            <div class="min-w-0 flex-1">
                                <p class="text-[11px] font-semibold text-emerald-700">Membahas barang</p>
                                <p class="text-sm text-slate-900 truncate">{{ $selectedProduct->title }}</p>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="text-sm font-bold text-emerald-700 tabular-nums">Rp{{ number_format($selectedProduct->price, 0, ',', '.') }}</p>
                                @if(strtolower($selectedProduct->status) !== 'available')
                                    <p class="text-[11px] text-slate-500">{{ strtolower($selectedProduct->status) === 'booked' ? 'Sedang di-booking' : 'Terjual' }}</p>
                                @endif
                            </div>
                        </div>
                    @endif

                    <!-- Riwayat pesan -->
                    <div id="chatScroll" class="flex-1 min-h-0 overflow-y-auto px-3 sm:px-6 py-4 space-y-2 bg-slate-50/60" aria-live="polite">
                        @php $prevDay = null; $prevProductId = null; @endphp
                        @forelse($messages as $m)
                            @php
                                $mine = (int) $m->sender_id === $me;
                                $day = $m->created_at->toDateString();
                            @endphp

                            @if($day !== $prevDay)
                                <div class="flex justify-center py-2">
                                    <span class="text-[11px] font-semibold text-slate-500 bg-white border border-slate-200 rounded-full px-3 py-0.5">{{ $dayLabel($m->created_at) }}</span>
                                </div>
                                @php $prevDay = $day; @endphp
                            @endif

                            @if($m->product && $m->product_id !== $prevProductId)
                                <div class="flex {{ $mine ? 'justify-end' : 'justify-start' }}">
                                    <span class="text-[11px] text-slate-500 bg-white border border-slate-200 rounded-md px-2 py-0.5 max-w-[75%] truncate">📦 {{ $m->product->title }}</span>
                                </div>
                            @endif
                            @php $prevProductId = $m->product_id ?: $prevProductId; @endphp

                            <div class="flex {{ $mine ? 'justify-end' : 'justify-start' }}">
                                <div class="max-w-[80%] sm:max-w-md rounded-2xl px-3.5 py-2 text-sm leading-relaxed shadow-sm {{ $mine ? 'bg-emerald-600 text-white rounded-br-md' : 'bg-white text-slate-800 border border-slate-200 rounded-bl-md' }}">
                                    <p class="whitespace-pre-line break-words">{{ $m->message }}</p>
                                    <p class="mt-0.5 text-[10px] text-right {{ $mine ? 'text-emerald-100' : 'text-slate-400' }}">
                                        {{ $m->created_at->format('H:i') }}
                                        @if($mine)
                                            <span aria-label="{{ $m->is_read ? 'Sudah dibaca' : 'Terkirim' }}">{{ $m->is_read ? '✓✓' : '✓' }}</span>
                                        @endif
                                    </p>
                                </div>
                            </div>
                        @empty
                            <div class="h-full flex flex-col items-center justify-center text-center px-6">
                                <p class="text-4xl mb-2" aria-hidden="true">👋</p>
                                <p class="font-semibold text-slate-800">Mulai percakapan dengan {{ $displayName($activeContact) }}</p>
                                <p class="text-sm text-slate-500 mt-1">Tanyakan kondisi barang, tawar harga, atau atur lokasi COD.</p>
                            </div>
                        @endforelse
                    </div>

                    <!-- Kirim pesan -->
                    <form action="{{ route('chat.send') }}" method="POST" class="border-t border-slate-200 bg-white px-3 sm:px-4 pt-2 pb-[max(0.75rem,env(safe-area-inset-bottom))]">
                        @csrf
                        <input type="hidden" name="receiver_id" value="{{ $activeContact->id }}">
                        @if($selectedProduct)
                            <input type="hidden" name="product_id" value="{{ $selectedProduct->id }}">
                        @endif

                        <div class="flex gap-2 overflow-x-auto no-scrollbar pb-2" aria-label="Balasan cepat">
                            @foreach(['Halo, barangnya masih ada?', 'Bisa nego?', 'Bisa COD di mana?', 'Boleh minta foto/video detail?'] as $quick)
                                <button type="button" onclick="useQuickReply(this)" class="shrink-0 text-xs font-medium text-emerald-700 border border-emerald-200 bg-emerald-50 hover:bg-emerald-100 rounded-full px-3 py-1">{{ $quick }}</button>
                            @endforeach
                        </div>

                        @error('message')
                            <p class="text-sm text-rose-600 mb-1" role="alert">{{ $message }}</p>
                        @enderror

                        <div class="flex items-end gap-2">
                            <label for="messageInput" class="sr-only">Tulis pesan</label>
                            <input type="text" id="messageInput" name="message" value="{{ old('message') }}" required maxlength="1000" autocomplete="off" placeholder="Tulis pesan..."
                                   class="flex-1 min-w-0 bg-slate-100 border-transparent rounded-full px-4 py-2.5 text-sm focus:bg-white focus:border-emerald-500 focus:ring-emerald-500">
                            <button type="submit" class="shrink-0 w-11 h-11 rounded-full bg-emerald-600 hover:bg-emerald-700 text-white flex items-center justify-center" aria-label="Kirim pesan">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                            </button>
                        </div>
                    </form>
                @else
                    <div class="flex-1 flex flex-col items-center justify-center text-center px-8">
                        <p class="text-5xl mb-3" aria-hidden="true">💬</p>
                        <p class="font-semibold text-slate-800">Pilih percakapan</p>
                        <p class="text-sm text-slate-500 mt-1 max-w-sm">Pilih kontak di sebelah kiri untuk melanjutkan diskusi, tawar harga, atau atur jadwal COD.</p>
                    </div>
                @endif
            </section>
        </div>
    </main>

    @if($activeContact)
        <script>
            // Mulai dari pesan terbaru
            const chatScroll = document.getElementById('chatScroll');
            chatScroll.scrollTop = chatScroll.scrollHeight;

            function useQuickReply(button) {
                const input = document.getElementById('messageInput');
                input.value = button.textContent.trim();
                input.focus();
            }
        </script>
    @endif
</x-market-layout>

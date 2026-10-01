<x-market-layout title="Request Barang">

    @php
        $me = Auth::id();
        $isSeller = Auth::user()->role === 'penjual';
        $openFormOnLoad = $errors->request->any();
        $input = 'w-full border-slate-300 rounded-lg text-sm focus:border-emerald-500 focus:ring-emerald-500';
    @endphp

    <main class="max-w-5xl mx-auto px-4 sm:px-6 py-4 sm:py-6 space-y-4">

        <!-- Ajakan -->
        <section class="rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 text-white px-4 py-4 sm:px-6 sm:py-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h1 class="text-lg sm:text-xl font-black leading-tight">Request Barang</h1>
                <p class="text-sm text-emerald-50 mt-0.5">
                    @if($isSeller)
                        Lihat barang yang sedang dicari pembeli di Batam, lalu tawarkan stokmu lewat chat.
                    @else
                        Belum nemu di katalog? Posting barang yang kamu cari, biar penjual di Batam yang menawarkan.
                    @endif
                </p>
            </div>
            <button type="button" onclick="openRequestModal()" class="shrink-0 inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-lg bg-white text-emerald-700 hover:bg-emerald-50 text-sm font-semibold">
                <span aria-hidden="true" class="text-lg leading-none">+</span> Buat Request
            </button>
        </section>

        @if(session('success'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">✅ {{ session('success') }}</div>
        @endif

        <!-- Tab -->
        <nav class="flex border-b border-slate-200" aria-label="Filter request">
            @foreach(['semua' => 'Sedang dicari', 'saya' => 'Request saya'] as $key => $label)
                <a href="{{ route('requests.index', $key === 'saya' ? ['tab' => 'saya'] : []) }}"
                   class="flex-1 sm:flex-none sm:px-6 text-center py-2.5 text-sm font-semibold border-b-2 -mb-px transition {{ $tab === $key ? 'border-emerald-600 text-emerald-700' : 'border-transparent text-slate-500 hover:text-slate-700' }}"
                   @if($tab === $key) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>

        <!-- Daftar request -->
        <div class="grid sm:grid-cols-2 gap-3">
            @forelse($requests as $req)
                @php
                    $isMine = (int) $req->user_id === $me;
                    $fulfilled = $req->status === 'Fulfilled';
                    $cat = $categories[$req->kategori] ?? null;
                @endphp
                <article class="rounded-xl border border-slate-200 bg-white p-4 flex flex-col {{ $fulfilled ? 'opacity-70' : '' }}">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs font-semibold text-emerald-700 bg-emerald-50 border border-emerald-100 px-2 py-0.5 rounded-full">
                            {{ $cat ? $cat['icon'] . ' ' . $cat['label'] : $req->kategori }}
                        </span>
                        @if($fulfilled)
                            <span class="text-xs font-semibold text-slate-600 bg-slate-100 px-2 py-0.5 rounded-full">✓ Sudah didapat</span>
                        @else
                            <span class="text-xs text-slate-400">{{ $req->created_at->locale('id')->diffForHumans() }}</span>
                        @endif
                    </div>

                    <h2 class="mt-2 font-semibold text-slate-900 leading-snug">{{ $req->nama_barang }}</h2>
                    <p class="mt-1 text-sm text-slate-600 line-clamp-3 whitespace-pre-line">{{ $req->deskripsi }}</p>

                    <dl class="mt-3 mb-3 grid grid-cols-2 gap-2 text-sm">
                        <div>
                            <dt class="text-xs text-slate-500">Budget maksimal</dt>
                            <dd class="font-bold text-emerald-700 tabular-nums">Rp{{ number_format($req->budget_maksimal, 0, ',', '.') }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500">Lokasi COD</dt>
                            <dd class="font-medium text-slate-700 truncate">📍 {{ $req->lokasi_cod }}</dd>
                        </div>
                    </dl>

                    <div class="mt-auto pt-3 flex items-center justify-between gap-2 border-t border-slate-100">
                        <p class="text-xs text-slate-500 truncate">Dicari oleh <span class="font-medium text-slate-700">{{ $isMine ? 'kamu' : ($req->user->name ?? 'Pembeli') }}</span></p>

                        @if($isMine)
                            @unless($fulfilled)
                                <form action="{{ route('requests.fulfill', $req) }}" method="POST" onsubmit="return confirm('Tandai request ini sudah didapat? Request tidak akan tampil lagi ke penjual.')">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="shrink-0 h-9 px-3 rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-50 text-sm font-semibold">Sudah dapat</button>
                                </form>
                            @endunless
                        @elseif(!$fulfilled)
                            <a href="{{ route('chat.index', ['user_id' => $req->user_id]) }}"
                               class="shrink-0 inline-flex items-center gap-1.5 h-9 px-3 rounded-lg {{ $isSeller ? 'bg-emerald-600 hover:bg-emerald-700 text-white' : 'border border-emerald-600 text-emerald-700 hover:bg-emerald-50' }} text-sm font-semibold">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12Z"/></svg>
                                {{ $isSeller ? 'Tawarkan barang' : 'Chat' }}
                            </a>
                        @endif
                    </div>
                </article>
            @empty
                <div class="sm:col-span-2 text-center py-14 rounded-xl border border-dashed border-slate-300">
                    <p class="text-4xl mb-2" aria-hidden="true">🔎</p>
                    @if($tab === 'saya')
                        <p class="font-bold text-slate-800">Kamu belum pernah membuat request</p>
                        <p class="text-sm text-slate-500 mt-1">Ceritakan barang yang kamu cari, penjual akan menghubungimu lewat chat.</p>
                    @else
                        <p class="font-bold text-slate-800">Belum ada request barang</p>
                        <p class="text-sm text-slate-500 mt-1">Jadilah yang pertama memposting barang incaranmu.</p>
                    @endif
                    <button type="button" onclick="openRequestModal()" class="mt-4 px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold">+ Buat Request</button>
                </div>
            @endforelse
        </div>
    </main>

    <!-- Form buat request -->
    <div id="requestModal" class="fixed inset-0 bg-slate-900/60 {{ $openFormOnLoad ? 'flex' : 'hidden' }} items-end sm:items-center justify-center z-50 sm:p-4" role="dialog" aria-modal="true" aria-labelledby="requestTitle">
        <div class="bg-white w-full sm:max-w-md rounded-t-2xl sm:rounded-2xl shadow-xl max-h-[92vh] overflow-y-auto">
            <div class="sticky top-0 bg-white flex justify-between items-center px-5 sm:px-6 py-4 border-b border-slate-100">
                <h3 id="requestTitle" class="text-base font-bold text-slate-900">Buat request barang</h3>
                <button type="button" onclick="closeRequestModal()" class="w-8 h-8 rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-600 text-xl" aria-label="Tutup">&times;</button>
            </div>

            <form action="{{ route('requests.store') }}" method="POST" class="px-5 sm:px-6 py-4 space-y-3.5">
                @csrf

                @if($errors->request->any())
                    <div class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2.5 text-sm text-rose-700" role="alert">
                        <p class="font-semibold">Request belum terkirim:</p>
                        <ul class="list-disc list-inside mt-0.5">
                            @foreach($errors->request->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div>
                    <label for="nama_barang" class="block text-sm font-semibold text-slate-700 mb-1">Barang yang dicari</label>
                    <input type="text" id="nama_barang" name="nama_barang" value="{{ old('nama_barang') }}" maxlength="255" required placeholder="Contoh: Kamera Sony A6000" class="{{ $input }}">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="kategori" class="block text-sm font-semibold text-slate-700 mb-1">Kategori</label>
                        <select id="kategori" name="kategori" required class="{{ $input }}">
                            @foreach($categories as $value => $cat)
                                <option value="{{ $value }}" @selected(old('kategori') === $value)>{{ $cat['icon'] }} {{ $cat['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="budget_maksimal" class="block text-sm font-semibold text-slate-700 mb-1">Budget maks. (Rp)</label>
                        <input type="number" id="budget_maksimal" name="budget_maksimal" value="{{ old('budget_maksimal') }}" min="0" step="1000" inputmode="numeric" required placeholder="2500000" class="{{ $input }}">
                    </div>
                </div>

                <div>
                    <label for="lokasi_cod" class="block text-sm font-semibold text-slate-700 mb-1">Lokasi COD yang diinginkan</label>
                    <input type="text" id="lokasi_cod" name="lokasi_cod" value="{{ old('lokasi_cod') }}" maxlength="255" required placeholder="Contoh: Batam Center / Nagoya Hill" class="{{ $input }}">
                </div>

                <div>
                    <label for="deskripsi" class="block text-sm font-semibold text-slate-700 mb-1">Kriteria barang</label>
                    <textarea id="deskripsi" name="deskripsi" rows="3" maxlength="2000" required placeholder="Ukuran, warna, kondisi minimal, kelengkapan..." class="{{ $input }}">{{ old('deskripsi') }}</textarea>
                </div>

                <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2.5 rounded-lg text-sm">Posting request</button>
            </form>
        </div>
    </div>

    <script>
        function openRequestModal() {
            const modal = document.getElementById('requestModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.getElementById('nama_barang').focus();
        }

        function closeRequestModal() {
            const modal = document.getElementById('requestModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        document.getElementById('requestModal').addEventListener('click', function (e) {
            if (e.target === this) closeRequestModal();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeRequestModal();
        });
    </script>
</x-market-layout>

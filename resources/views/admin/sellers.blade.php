<x-market-layout title="Panel Admin">

    @php
        $tabs = [
            'pending'  => 'Menunggu',
            'verified' => 'Terverifikasi',
            'rejected' => 'Ditolak',
        ];
        $emptyText = [
            'pending'  => 'Tidak ada penjual yang menunggu verifikasi. Semua sudah ditinjau 🎉',
            'verified' => 'Belum ada penjual yang terverifikasi.',
            'rejected' => 'Belum ada penjual yang ditolak.',
        ];
    @endphp

    <main class="max-w-5xl mx-auto px-4 sm:px-6 py-4 sm:py-6 space-y-4">

        <div>
            <h1 class="text-lg sm:text-xl font-bold text-slate-900">Verifikasi penjual</h1>
            <p class="text-sm text-slate-500 mt-0.5">Cocokkan foto KTP dengan selfie sebelum menyetujui. Penjual baru bisa menayangkan barang setelah disetujui.</p>
        </div>

        @if(session('success'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">✅ {{ session('success') }}</div>
        @endif

        <!-- Tab status -->
        <nav class="flex border-b border-slate-200" aria-label="Status verifikasi">
            @foreach($tabs as $key => $label)
                <a href="{{ route('admin.sellers', $key === 'pending' ? [] : ['status' => $key]) }}"
                   class="flex-1 sm:flex-none sm:px-6 text-center py-2.5 text-sm font-semibold border-b-2 -mb-px transition {{ $status === $key ? 'border-emerald-600 text-emerald-700' : 'border-transparent text-slate-500 hover:text-slate-700' }}"
                   @if($status === $key) aria-current="page" @endif>
                    {{ $label }}
                    <span class="ml-1 text-xs font-bold px-1.5 py-0.5 rounded-full {{ $key === 'pending' && ($counts[$key] ?? 0) > 0 ? 'bg-amber-100 text-amber-800' : ($status === $key ? 'bg-emerald-100' : 'bg-slate-100') }}">{{ $counts[$key] ?? 0 }}</span>
                </a>
            @endforeach
        </nav>

        <!-- Daftar penjual -->
        <div class="space-y-3">
            @forelse($sellers as $seller)
                @php $shopName = $seller->nama_toko ?: $seller->name; @endphp
                <article class="rounded-xl border border-slate-200 bg-white overflow-hidden">
                    <div class="p-4 flex items-start gap-3">
                        <span class="w-11 h-11 shrink-0 rounded-full bg-emerald-600 text-white font-bold flex items-center justify-center" aria-hidden="true">
                            {{ strtoupper(substr($shopName, 0, 1)) }}
                        </span>
                        <div class="flex-1 min-w-0">
                            <h2 class="font-semibold text-slate-900 truncate">{{ $shopName }}</h2>
                            <p class="text-sm text-slate-600 truncate">{{ $seller->name }} · {{ $seller->email }}</p>
                            <p class="text-xs text-slate-500 mt-0.5">
                                📍 {{ $seller->lokasi_lapak ?: '-' }}
                                @if($seller->phone_number) · 📱 {{ $seller->phone_number }} @endif
                                · Daftar {{ $seller->created_at->locale('id')->diffForHumans() }}
                            </p>
                        </div>
                    </div>

                    <!-- Dokumen KYC -->
                    <div class="px-4 grid grid-cols-2 gap-3">
                        @foreach([
                            ['KTP', $seller->ktp_photo_path, 'admin.sellerKtp'],
                            ['Selfie dengan KTP', $seller->selfie_path, 'admin.sellerSelfie'],
                        ] as [$docLabel, $docPath, $docRoute])
                            <figure>
                                @if($docPath)
                                    <a href="{{ route($docRoute, $seller->id) }}" target="_blank" rel="noopener" class="block aspect-[4/3] rounded-lg overflow-hidden border border-slate-200 bg-slate-100 hover:opacity-90" title="Buka {{ $docLabel }} ukuran penuh">
                                        <img src="{{ route($docRoute, $seller->id) }}" alt="{{ $docLabel }} {{ $seller->name }}" loading="lazy" class="w-full h-full object-cover">
                                    </a>
                                @else
                                    <div class="aspect-[4/3] rounded-lg border border-dashed border-slate-300 bg-slate-50 flex items-center justify-center text-xs text-slate-400 text-center px-2">Tidak ada file</div>
                                @endif
                                <figcaption class="mt-1 text-xs text-slate-500">{{ $docLabel }}</figcaption>
                            </figure>
                        @endforeach
                    </div>

                    <!-- Aksi sesuai status -->
                    <div class="p-4 flex flex-wrap justify-end gap-2">
                        @if($seller->seller_status !== 'verified')
                            <form action="{{ route('admin.verifySeller', $seller->id) }}" method="POST">
                                @csrf
                                <button type="submit" class="h-10 px-4 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold">✓ Setujui</button>
                            </form>
                        @endif
                        @if($seller->seller_status === 'pending')
                            <form action="{{ route('admin.rejectSeller', $seller->id) }}" method="POST" onsubmit="return confirm('Tolak verifikasi {{ addslashes($shopName) }}? Penjual tidak akan bisa menayangkan barang.')">
                                @csrf
                                <button type="submit" class="h-10 px-4 rounded-lg border border-rose-300 text-rose-700 hover:bg-rose-50 text-sm font-semibold">Tolak</button>
                            </form>
                        @elseif($seller->seller_status === 'verified')
                            <form action="{{ route('admin.rejectSeller', $seller->id) }}" method="POST" onsubmit="return confirm('Cabut verifikasi {{ addslashes($shopName) }}? Penjual tidak akan bisa menayangkan barang baru.')">
                                @csrf
                                <button type="submit" class="h-10 px-4 rounded-lg border border-rose-300 text-rose-700 hover:bg-rose-50 text-sm font-semibold">Cabut verifikasi</button>
                            </form>
                        @endif
                    </div>
                </article>
            @empty
                <div class="text-center py-14 rounded-xl border border-dashed border-slate-300">
                    <p class="text-4xl mb-2" aria-hidden="true">🛡️</p>
                    <p class="text-sm text-slate-600">{{ $emptyText[$status] }}</p>
                </div>
            @endforelse
        </div>
    </main>
</x-market-layout>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Verifikasi Penjual thriftLO</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 font-sans antialiased p-6">
    <div class="max-w-6xl mx-auto bg-white rounded-3xl shadow-xl overflow-hidden border border-gray-100 p-8">

        <div class="flex justify-between items-center mb-6 border-b pb-4">
            <div>
                <h1 class="text-2xl font-black text-gray-800">🛡️ Admin Panel: Verifikasi KYC Penjual</h1>
                <p class="text-xs text-gray-500">Kelola dan tinjau dokumen pendaftaran toko preloved di Batam.</p>
            </div>
            <a href="{{ route('home') }}" class="bg-gray-200 text-gray-700 font-bold px-4 py-2 rounded-xl text-xs hover:bg-gray-300 transition">← Kembali ke Katalog</a>
        </div>

        @if(session('success'))
            <div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-800 p-4 rounded-xl text-xs font-bold">
                {{ session('success') }}
            </div>
        @endif

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-gray-50 text-gray-700 uppercase tracking-wider border-b">
                        <th class="p-3">Nama & Email</th>
                        <th class="p-3">Nama Toko & Lokasi</th>
                        <th class="p-3">Dokumen KTP / Selfie</th>
                        <th class="p-3">Status KYC</th>
                        <th class="p-3 text-center">Aksi Admin</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($pendingSellers as $seller)
                        <tr class="hover:bg-gray-50">
                            <td class="p-3">
                                <span class="font-bold block text-gray-900">{{ $seller->name }}</span>
                                <span class="text-gray-400 text-[11px]">{{ $seller->email }}</span>
                                <span class="block text-emerald-600 font-mono text-[10px]">{{ $seller->phone_number }}</span>
                            </td>
                            <td class="p-3">
                                <span class="font-bold text-emerald-800 block">{{ $seller->nama_toko ?? '-' }}</span>
                                <span class="text-gray-500 text-[11px]">📍 {{ $seller->lokasi_lapak ?? '-' }}</span>
                            </td>
                            <td class="p-3">
                                @if($seller->selfie_path)
                                    <a href="{{ asset('storage/' . $seller->selfie_path) }}" target="_blank" class="text-indigo-600 font-bold underline hover:text-indigo-800">Lihat Foto Selfie</a>
                                @else
                                    <span class="text-gray-400">Tidak ada file</span>
                                @endif
                            </td>
                            <td class="p-3">
                                @if($seller->seller_status === 'verified')
                                    <span class="bg-emerald-100 text-emerald-800 px-2 py-1 rounded-full font-extrabold text-[10px]">VERIFIED</span>
                                @elseif($seller->seller_status === 'rejected')
                                    <span class="bg-rose-100 text-rose-800 px-2 py-1 rounded-full font-extrabold text-[10px]">REJECTED</span>
                                @else
                                    <span class="bg-amber-100 text-amber-800 px-2 py-1 rounded-full font-extrabold text-[10px]">PENDING</span>
                                @endif
                            </td>
                            <td class="p-3 text-center space-x-2">
                                <form action="{{ route('admin.verifySeller', $seller->id) }}" method="POST" class="inline-block">
                                    @csrf
                                    <button type="submit" class="bg-emerald-600 text-white font-bold px-3 py-1.5 rounded-lg hover:bg-emerald-700 transition text-[11px]">
                                        ✔ Setujui
                                    </button>
                                </form>
                                <form action="{{ route('admin.rejectSeller', $seller->id) }}" method="POST" class="inline-block">
                                    @csrf
                                    <button type="submit" class="bg-rose-600 text-white font-bold px-3 py-1.5 rounded-lg hover:bg-rose-700 transition text-[11px]">
                                        ❌ Tolak
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-6 text-center text-gray-400">Belum ada penjual yang terdaftar.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    </div>
</body>
</html>

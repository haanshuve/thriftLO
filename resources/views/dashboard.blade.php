<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h2 class="font-black text-xl text-gray-800 leading-tight flex items-center gap-2">
                    🏬 Dashboard Penjual
                    @if(Auth::user()->seller_status === 'verified')
                        <span class="bg-emerald-100 text-emerald-800 text-[10px] font-bold px-2.5 py-0.5 rounded-full border border-emerald-200 shadow-xs">
                            ✓ Verified Seller
                        </span>
                    @else
                        <span class="bg-amber-100 text-amber-800 text-[10px] font-bold px-2.5 py-0.5 rounded-full border border-amber-200">
                            ⏳ Pending Verification
                        </span>
                    @endif
                </h2>
                <p class="text-xs text-gray-500 mt-1">Kelola stok preloved, unggah video proof, dan verifikasi Token QR COD Pembeli.</p>
            </div>

            <div>
                @if(Auth::user()->seller_status === 'verified')
                    <button onclick="document.getElementById('uploadModal').classList.remove('hidden')" class="bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs px-4 py-2.5 rounded-xl shadow transition flex items-center gap-2">
                        + Jual Barang Baru
                    </button>
                @else
                    <button onclick="alert('Verifikasi dokumen KYC kamu masih diproses Admin thriftLO. Kamu belum bisa menambah barang jualan.')" class="bg-gray-400 text-white font-extrabold text-xs px-4 py-2.5 rounded-xl shadow cursor-not-allowed flex items-center gap-2">
                        🔒 Form Terkunci (Pending KYC)
                    </button>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <!-- Banner Notifikasi Alert Status -->
            @if(session('success'))
                <div class="bg-emerald-600 text-white p-4 rounded-2xl shadow font-bold text-sm">
                    🎉 {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="bg-rose-500 text-white p-4 rounded-2xl shadow font-bold text-sm">
                    ⚠️ {{ session('error') }}
                </div>
            @endif

            <!-- Banner Status Verifikasi Identitas (KYC Penjual) -->
            @if(Auth::user()->role === 'penjual')
                @if(Auth::user()->seller_status === 'pending')
                    <div class="bg-amber-500 text-white p-4.5 rounded-2xl shadow-md border border-amber-400 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <span class="text-3xl">⏳</span>
                            <div>
                                <h4 class="font-extrabold text-sm">Verifikasi Dokumen KYC Dalam Antrean</h4>
                                <p class="text-xs text-amber-100 mt-0.5">Dokumen KTP & Selfie kamu sedang ditinjau oleh Admin thriftLO Batam (Maksimal 1x24 Jam).</p>
                            </div>
                        </div>
                        <span class="bg-amber-700/80 text-white text-[10px] font-black px-3 py-1 rounded-full uppercase tracking-wider border border-amber-300">
                            Status: Pending
                        </span>
                    </div>
                @elseif(Auth::user()->seller_status === 'verified')
                    <div class="bg-emerald-600 text-white p-4.5 rounded-2xl shadow-md border border-emerald-500 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <span class="text-3xl">🛡️</span>
                            <div>
                                <h4 class="font-extrabold text-sm">Akun Penjual Terverifikasi (Verified Seller)</h4>
                                <p class="text-xs text-emerald-100 mt-0.5">Identitas Toko <strong>"{{ Auth::user()->nama_toko ?? 'Lapak Batam' }}"</strong> telah tervalidasi amanah. Kamu bebas memasarkan produk eceran maupun borongan.</p>
                            </div>
                        </div>
                        <span class="bg-white text-emerald-900 text-[10px] font-black px-3 py-1 rounded-full uppercase shadow">
                            Verified
                        </span>
                    </div>
                @endif
            @endif

            <!-- Modul Verifikasi QR Code COD -->
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-emerald-100 bg-gradient-to-r from-emerald-50/50 to-white">
                <span class="text-[10px] font-extrabold text-emerald-700 bg-emerald-100 px-2.5 py-0.5 rounded uppercase tracking-wider">Keamanan Transaksi O2O</span>
                <h3 class="text-sm font-bold text-gray-800 mt-1.5">🔍 Verifikasi Token QR Code COD Batam</h3>
                <p class="text-xs text-gray-500 mb-4">Masukkan Token QR yang ditunjukkan Pembeli saat bertemu di lokasi COD Batam untuk memvalidasi transaksi dan menyelesaikan pesanan.</p>

                <form action="{{ route('booking.verify', ['id' => 1]) }}" method="POST" class="flex flex-col sm:flex-row gap-3">
                    @csrf
                    <input type="text" name="qr_code_token" placeholder="CONTOH: TL-QWZ6TK1F" required class="w-full sm:w-80 border-gray-200 rounded-xl text-xs uppercase font-mono font-bold p-3 border focus:ring-emerald-500">
                    <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs px-5 py-3 rounded-xl transition shadow-sm uppercase tracking-wider">
                        Verifikasi QR
                    </button>
                </form>
            </div>

            <!-- Tabel Inventaris Stok Penjual -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="p-4 border-b border-gray-100 flex justify-between items-center">
                    <div>
                        <h4 class="font-bold text-gray-800 text-sm flex items-center gap-2">📦 Inventaris Stok Saya</h4>
                        <span class="text-xs text-gray-400 font-semibold">Total: {{ isset($myProducts) ? $myProducts->count() : 0 }} Barang Ditayangkan</span>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-gray-600">
                        <thead class="bg-gray-50 text-gray-700 font-bold uppercase text-[10px]">
                            <tr>
                                <th class="p-3.5">Barang & Video Proof</th>
                                <th class="p-3.5">Mode & Kategori</th>
                                <th class="p-3.5">Harga Jual</th>
                                <th class="p-3.5">Grade Fisik</th>
                                <th class="p-3.5">Status Barang</th>
                                <th class="p-3.5 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($myProducts ?? [] as $item)
                                <tr class="hover:bg-gray-50/50 transition">
                                    <td class="p-3.5 font-bold text-gray-800 flex items-center gap-3">
                                        <img src="{{ filter_var($item->image_url, FILTER_VALIDATE_URL) ? $item->image_url : asset('storage/' . $item->image_url) }}" class="w-10 h-10 rounded-xl object-cover border border-gray-200 shadow-xs" onerror="this.src='https://images.unsplash.com/photo-1523381210434-271e8be1f52b?auto=format&fit=crop&w=200&q=80'">
                                        <div>
                                            <span class="block text-sm text-gray-800 font-bold">{{ $item->nama_barang ?? $item->title }}</span>
                                            @if($item->video_proof_url ?? $item->video_proof)
                                                <a href="{{ $item->video_proof_url ?? $item->video_proof }}" target="_blank" class="text-[10px] text-blue-600 hover:underline font-semibold flex items-center gap-1 mt-0.5">
                                                    🎥 Video Proof Disertakan
                                                </a>
                                            @else
                                                <span class="text-[10px] text-emerald-600 font-semibold">✓ Fisik Verified</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="p-3.5">
                                        <span class="font-extrabold uppercase text-[10px] px-2 py-0.5 rounded {{ $item->mode_jual == 'borongan' ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800' }}">
                                            {{ $item->mode_jual == 'borongan' ? '📦 BORONGAN' : '🛍️ ECERAN' }}
                                        </span>
                                        <span class="block text-[11px] text-gray-500 mt-1 font-medium">{{ $item->kategori }}</span>
                                    </td>
                                    <td class="p-3.5 font-extrabold text-emerald-700 text-sm">
                                        Rp {{ number_format($item->harga ?? $item->price, 0, ',', '.') }}
                                    </td>
                                    <td class="p-3.5 font-semibold">
                                        <span class="bg-gray-100 px-2 py-1 rounded-md text-gray-700 text-[11px] font-bold">{{ $item->grade }}</span>
                                    </td>
                                    <td class="p-3.5">
                                        @if(strtolower($item->status) == 'available')
                                            <span class="bg-emerald-100 text-emerald-800 font-bold px-2.5 py-1 rounded-lg text-[11px]">Available</span>
                                        @elseif(strtolower($item->status) == 'booked')
                                            <span class="bg-amber-100 text-amber-800 font-bold px-2.5 py-1 rounded-lg text-[11px]">Booked (Janji COD)</span>
                                        @else
                                            <span class="bg-gray-200 text-gray-600 font-bold px-2.5 py-1 rounded-lg text-[11px]">Sold Out</span>
                                        @endif
                                    </td>
                                    <!-- Tombol Hapus Produk -->
                                    <td class="p-3.5 text-center">
                                        <form action="{{ route('product.destroy', $item->id) }}" method="POST" onsubmit="return confirm('Yakin ingin menghapus produk ini dari katalog?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="bg-rose-100 hover:bg-rose-200 text-rose-700 px-3 py-1.5 rounded-xl font-bold text-[11px] transition shadow-xs inline-flex items-center gap-1">
                                                🗑️ Hapus
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="p-8 text-center text-gray-400">
                                        <span class="text-3xl block mb-2">📦</span>
                                        Belum ada barang yang kamu tayangkan. Klik <strong>"+ Jual Barang Baru"</strong> di atas.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tabel Janji Temu COD Aktif -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden p-5">
                <h4 class="font-bold text-gray-800 text-sm mb-3">🤝 Daftar Janji Temu COD (Booking Pembeli)</h4>
                <div class="space-y-3">
                    @forelse($myBookings ?? [] as $booking)
                        <div class="p-3.5 bg-gray-50 rounded-xl border border-gray-200 flex justify-between items-center text-xs">
                            <div>
                                {{-- Token sengaja disamarkan: penjual harus memindai/meminta token dari HP pembeli saat COD --}}
                                <span class="font-mono font-bold bg-blue-100 text-blue-800 px-2 py-0.5 rounded text-[10px]" title="Minta pembeli menunjukkan token QR saat bertemu">
                                    TOKEN: TL-••••••••
                                </span>
                                <h5 class="font-bold text-gray-800 text-sm mt-1">{{ $booking->product->nama_barang ?? $booking->product->title ?? 'Produk' }}</h5>
                                <p class="text-gray-500 mt-0.5">📍 Lokasi: <strong>{{ $booking->cod_location }}</strong> | ⏰ Waktu: {{ $booking->cod_schedule }}</p>
                            </div>
                            <span class="font-bold px-2.5 py-1 rounded-lg {{ $booking->status_cod == 'Completed' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                {{ $booking->status_cod }}
                            </span>
                        </div>
                    @empty
                        <p class="text-xs text-gray-400 text-center py-4">Belum ada janji temu COD yang aktif saat ini.</p>
                    @endforelse
                </div>
            </div>

        </div>
    </div>

    <!-- Modal Form Tambah Barang Baru -->
    <div id="uploadModal" class="fixed inset-0 bg-black/60 hidden flex items-center justify-center z-50 p-4 backdrop-blur-xs">
        <div class="bg-white p-6 rounded-3xl max-w-lg w-full shadow-2xl max-h-[90vh] overflow-y-auto border border-gray-100">
            <div class="flex justify-between items-center mb-4 border-b pb-3 border-gray-100">
                <h3 class="text-base font-black text-gray-800">📦 Form Upload Barang Preloved</h3>
                <button onclick="document.getElementById('uploadModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600 text-2xl font-bold">&times;</button>
            </div>

            <form action="{{ route('product.store') }}" method="POST" enctype="multipart/form-data" class="space-y-3.5">
                @csrf
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Nama Barang</label>
                    <input type="text" name="nama_barang" placeholder="Contoh: Digicam Sony CyberShot / Kaos Vintage" required class="w-full border-gray-200 rounded-xl p-2.5 text-xs focus:ring-emerald-500 border">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Mode Jual</label>
                        <select name="mode_jual" class="w-full border-gray-200 rounded-xl p-2.5 text-xs focus:ring-emerald-500 border">
                            <option value="ecer">Eceran (C2C)</option>
                            <option value="borongan">Borongan / Paket (B2B)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Kategori</label>
                        <select name="kategori" class="w-full border-gray-200 rounded-xl p-2.5 text-xs focus:ring-emerald-500 border">
                            <option value="Fashion">Fashion / Pakaian</option>
                            <option value="Vintage Tech">Vintage Tech (Gadget)</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Harga (Rp)</label>
                        <input type="number" name="harga" placeholder="150000" required class="w-full border-gray-200 rounded-xl p-2.5 text-xs focus:ring-emerald-500 border">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Grade Kondisi</label>
                        <select name="grade" class="w-full border-gray-200 rounded-xl p-2.5 text-xs focus:ring-emerald-500 border">
                            <option value="Grade A (Like New)">Grade A (Like New)</option>
                            <option value="Grade B (Minus Pemakaian)">Grade B (Minus Pemakaian)</option>
                            <option value="Grade C (Need Repair)">Grade C (Need Repair)</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Upload Foto Produk</label>
                    <input type="file" name="image" accept="image/*" required class="w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">URL Video Proof (Youtube/Drive)</label>
                    <input type="url" name="video_proof_url" placeholder="https://youtube.com/..." class="w-full border-gray-200 rounded-xl p-2.5 text-xs focus:ring-emerald-500 border">
                    <span class="text-[10px] text-gray-400 block mt-0.5">Membantu pembeli memverifikasi kondisi fisik asli.</span>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Deskripsi Detail</label>
                    <textarea name="deskripsi" rows="2" placeholder="Jelaskan kondisi fisik barang..." class="w-full border-gray-200 rounded-xl p-2.5 text-xs focus:ring-emerald-500 border"></textarea>
                </div>
                <button type="submit" class="w-full bg-emerald-600 text-white font-extrabold py-3 rounded-xl hover:bg-emerald-700 transition text-xs shadow-md uppercase tracking-wider mt-2">
                    Tayangkan Barang ke Katalog
                </button>
            </form>
        </div>
    </div>

    <!-- KOMPONEN FLOATING CHAT WIDGET -->
    <x-floating-chat />

</x-app-layout>

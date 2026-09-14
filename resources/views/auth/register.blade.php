<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun & Verifikasi - thriftLO Batam</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 font-sans antialiased flex items-center justify-center min-h-screen p-4">

    <div class="max-w-xl w-full bg-white rounded-3xl shadow-xl overflow-hidden border border-gray-100 my-6">

        <!-- Header Card -->
        <div class="bg-gradient-to-r from-emerald-600 to-teal-700 text-white p-6 text-center">
            <div class="inline-flex items-center justify-center w-12 h-12 bg-white/10 backdrop-blur-md rounded-2xl mb-2 text-2xl shadow-inner border border-white/20">
                🌱
            </div>
            <h2 class="text-2xl font-black tracking-wide">thriftLO <span class="text-xs bg-emerald-900/60 px-2 py-0.5 rounded font-mono">Batam</span></h2>
            <p class="text-emerald-100 text-xs mt-1 font-medium">Portal Pendaftaran Akun & Otentikasi Keamanan</p>
        </div>

        <div class="p-6 sm:p-8">
            <form method="POST" action="{{ route('register') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf

                <!-- LOKASI PILIHAN ROLE (PEMBELI vs PENJUAL) -->
                <div>
                    <label class="block text-xs font-black text-gray-700 mb-2 uppercase tracking-wider">Tahap 1: Pilih Peran Akun</label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="cursor-pointer">
                            <input type="radio" name="role" value="pembeli" id="rolePembeli" checked onchange="toggleSellerSection()" class="peer sr-only">
                            <div class="p-3 text-center border-2 rounded-2xl border-gray-200 peer-checked:border-emerald-600 peer-checked:bg-emerald-50/50 transition">
                                <span class="text-xl block mb-1">🛍️</span>
                                <span class="block text-xs font-black text-gray-800">Pembeli</span>
                                <span class="block text-[10px] text-gray-400">Thrift Hunter</span>
                            </div>
                        </label>

                        <label class="cursor-pointer">
                            <input type="radio" name="role" value="penjual" id="rolePenjual" onchange="toggleSellerSection()" class="peer sr-only">
                            <div class="p-3 text-center border-2 rounded-2xl border-gray-200 peer-checked:border-emerald-600 peer-checked:bg-emerald-50/50 transition">
                                <span class="text-xl block mb-1">🏬</span>
                                <span class="block text-xs font-black text-gray-800">Penjual</span>
                                <span class="block text-[10px] text-gray-400">Verified Consignor</span>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- TAHAP 2: DATA AKUN DASAR -->
                <div class="pt-2">
                    <label class="block text-xs font-black text-gray-700 mb-2 uppercase tracking-wider">Tahap 2: Informasi Akun & Kontak</label>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3">
                        <div>
                            <label class="block text-xs font-extrabold text-gray-700 mb-1">Nama Lengkap</label>
                            <input type="text" name="name" value="{{ old('name') }}" required placeholder="Contoh: Budi Santoso" class="w-full border-gray-200 rounded-xl p-2.5 text-xs focus:ring-emerald-500 border">
                        </div>
                        <div>
                            <label class="block text-xs font-extrabold text-gray-700 mb-1">No. WhatsApp Active (OTP)</label>
                            <input type="text" name="phone_number" value="{{ old('phone_number') }}" required placeholder="0812xxxxxxx" class="w-full border-gray-200 rounded-xl p-2.5 text-xs focus:ring-emerald-500 border">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="block text-xs font-extrabold text-gray-700 mb-1">Alamat Email</label>
                        <input type="email" name="email" value="{{ old('email') }}" required placeholder="nama@email.com" class="w-full border-gray-200 rounded-xl p-2.5 text-xs focus:ring-emerald-500 border">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-extrabold text-gray-700 mb-1">Kata Sandi</label>
                            <input type="password" name="password" required placeholder="Minimal 8 karakter" class="w-full border-gray-200 rounded-xl p-2.5 text-xs focus:ring-emerald-500 border">
                        </div>
                        <div>
                            <label class="block text-xs font-extrabold text-gray-700 mb-1">Konfirmasi Sandi</label>
                            <input type="password" name="password_confirmation" required placeholder="Ulangi sandi" class="w-full border-gray-200 rounded-xl p-2.5 text-xs focus:ring-emerald-500 border">
                        </div>
                    </div>
                </div>

                <!-- TAHAP 3: DOKUMEN OTENTIKASI PENJUAL (STRICT KYC) -->
                <div id="sellerKYCSection" class="hidden space-y-3 pt-4 border-t border-emerald-200 bg-emerald-50/60 p-4 rounded-2xl border">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-xs font-black text-emerald-900 uppercase tracking-wider flex items-center gap-1">
                            🛡️ Tahap 3: Otentikasi Dokumen Penjual (KYC)
                        </span>
                        <span class="text-[9px] bg-emerald-200 text-emerald-800 font-extrabold px-2 py-0.5 rounded">Wajib Isi</span>
                    </div>
                    <p class="text-[11px] text-emerald-700 leading-tight">Diperlukan validasi data diri resmi untuk mencegah penipuan barang bekas/fiktif di Batam.</p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2">
                        <div>
                            <label class="block text-xs font-extrabold text-gray-700 mb-1">Nama Toko / Lapak</label>
                            <input type="text" id="nama_toko" name="nama_toko" placeholder="Contoh: Batam Vintage Hub" class="w-full border-gray-200 rounded-xl p-2.5 text-xs border bg-white">
                        </div>
                        <div>
                            <label class="block text-xs font-extrabold text-gray-700 mb-1">Lokasi Lapak / COD Batam</label>
                            <input type="text" id="lokasi_lapak" name="lokasi_lapak" placeholder="Contoh: Batam Center / Sekupang" class="w-full border-gray-200 rounded-xl p-2.5 text-xs border bg-white">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                        <div>
                            <label class="block text-xs font-extrabold text-gray-700 mb-1">📷 Upload Foto KTP / MHS</label>
                            <input type="file" id="ktp_photo" name="ktp_photo" accept="image/*" class="w-full text-xs text-gray-500 bg-white border border-gray-200 rounded-xl p-2">
                            <span class="text-[10px] text-gray-400 block mt-0.5">Format JPG/PNG maks 2MB</span>
                        </div>
                        <div>
                            <label class="block text-xs font-extrabold text-gray-700 mb-1">🤳 Selfie Memegang Identitas</label>
                            <input type="file" id="selfie_ktp" name="selfie_ktp" accept="image/*" class="w-full text-xs text-gray-500 bg-white border border-gray-200 rounded-xl p-2">
                            <span class="text-[10px] text-gray-400 block mt-0.5">Wajah & KTP terlihat jelas</span>
                        </div>
                    </div>

                    <div class="pt-2">
                        <label class="flex items-start gap-2 cursor-pointer">
                            <input type="checkbox" id="kycAgreement" class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500 mt-0.5">
                            <span class="text-[11px] text-gray-600 font-medium">Saya menjamin barang preloved yang saya jual asli/nyata dan bersedia disanksi jika melakukan fraud.</span>
                        </label>
                    </div>
                </div>

                <!-- Tombol Submit -->
                <div class="pt-2">
                    <button type="submit" class="w-full bg-emerald-600 text-white font-extrabold py-3 rounded-xl hover:bg-emerald-700 transition shadow-lg text-xs uppercase tracking-wider">
                        🚀 Selesaikan Pendaftaran
                    </button>
                </div>
            </form>

            <div class="mt-4 text-center border-t pt-4 border-gray-100">
                <p class="text-xs text-gray-500">Sudah punya akun? <a href="{{ route('login') }}" class="text-emerald-600 font-bold hover:underline">Masuk / Login</a></p>
            </div>
        </div>
    </div>

    <!-- SCRIPT JAVASCRIPT DINAMIS UNTUK MEMUNCULKAN KYC -->
    <script>
        function toggleSellerSection() {
            const isPenjual = document.getElementById('rolePenjual').checked;
            const kycSection = document.getElementById('sellerKYCSection');
            const inputs = ['nama_toko', 'lokasi_lapak', 'ktp_photo', 'selfie_ktp', 'kycAgreement'];

            if (isPenjual) {
                kycSection.classList.remove('hidden');
                inputs.forEach(id => {
                    const el = document.getElementById(id);
                    if(el) el.setAttribute('required', 'required');
                });
            } else {
                kycSection.classList.add('hidden');
                inputs.forEach(id => {
                    const el = document.getElementById(id);
                    if(el) el.removeAttribute('required');
                });
            }
        }

        // Jalankan saat pertama kali halaman dimuat
        document.addEventListener('DOMContentLoaded', function() {
            toggleSellerSection();
        });
    </script>
</body>
</html>

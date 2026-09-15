<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-bold text-xl text-gray-800 leading-tight flex items-center gap-2">
                🎟️ Tiket & Riwayat Booking COD Saya
            </h2>
            <a href="{{ route('home') }}" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs px-3.5 py-2 rounded-xl transition">
                ← Kembali ke Katalog
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <!-- Alert Notifikasi  -->
            @if(session('success'))
                <div class="bg-emerald-600 text-white p-4 rounded-2xl shadow font-bold text-sm">
                    🎉 {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="bg-rose-600 text-white p-4 rounded-2xl shadow font-bold text-sm">
                    ⚠️ {{ session('error') }}
                </div>
            @endif

            <!-- Daftar Tiket Booking -->
            <div class="space-y-4">
                @forelse($bookings as $b)
                    <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 flex flex-col justify-between gap-4">

                        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                            <div class="flex items-start gap-4">
                                <img src="{{ filter_var($b->product->image_url ?? $b->product->image_path, FILTER_VALIDATE_URL) ? ($b->product->image_url ?? $b->product->image_path) : asset('storage/' . ($b->product->image_url ?? $b->product->image_path)) }}" class="w-16 h-16 rounded-2xl object-cover border border-gray-200 shrink-0" onerror="this.src='https://images.unsplash.com/photo-1523381210434-271e8be1f52b?auto=format&fit=crop&w=200&q=80'">
                                <div>
                                    <div class="flex items-center gap-2 mb-1">
                                        <!-- Disesuaikan menggunakan $b->qr_token sesuai database -->
                                        <span class="text-[10px] font-mono font-black bg-blue-100 text-blue-800 px-2.5 py-0.5 rounded-md border border-blue-200">
                                            TOKEN: {{ $b->qr_token }}
                                        </span>
                                        <span class="text-xs text-gray-400">Penjual: <strong>{{ $b->product->user->name ?? 'Penjual Batam' }}</strong></span>
                                    </div>
                                    <h4 class="font-bold text-gray-800 text-base">{{ $b->product->nama_barang ?? $b->product->title }}</h4>
                                    <p class="text-xs text-gray-500 mt-1">📍 <strong>Lokasi COD:</strong> {{ $b->cod_location }}</p>
                                    <p class="text-xs text-gray-500">⏰ <strong>Jadwal:</strong> {{ $b->cod_schedule }}</p>
                                </div>
                            </div>

                            <div class="flex flex-col items-end gap-2 w-full md:w-auto border-t md:border-t-0 pt-3 md:pt-0 border-gray-100">
                                <span class="text-base font-extrabold text-emerald-700">
                                    Rp {{ number_format($b->product->harga ?? $b->product->price, 0, ',', '.') }}
                                </span>
                                @if($b->status_cod == 'Completed')
                                    <span class="bg-emerald-100 text-emerald-800 font-extrabold text-xs px-3 py-1 rounded-xl border border-emerald-200">
                                        ✓ Selesai / Terverifikasi
                                    </span>
                                @else
                                    <span class="bg-amber-100 text-amber-800 font-extrabold text-xs px-3 py-1 rounded-xl border border-amber-200">
                                        ⏳ Menunggu Pertemuan untuk COD
                                    </span>
                                @endif
                            </div>
                        </div>

                        <!-- TAMPILAN KOTAK QR CODE -->
                        @if($b->status_cod !== 'Completed')
                            <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 text-center max-w-xs mx-auto w-full my-2">
                                <p class="text-[10px] text-slate-500 font-extrabold uppercase mb-2">Tunjukkan Token / QR ini saat bertemu Penjual:</p>
                                <img src="https://api.qrserver.com/v1/create-qr-code/?size=110x110&data={{ $b->qr_token }}" alt="QR Code" class="mx-auto border p-2 rounded-lg bg-white shadow-xs">
                                <span class="block font-mono font-black text-xs text-slate-800 mt-2 tracking-widest">{{ $b->qr_token }}</span>
                            </div>
                        @endif

                        <!-- MODUL REVIEW & RATING BINTANG  -->
                        @if($b->status_cod == 'Completed')
                            @php
                                $review = \App\Models\Review::where('booking_id', $b->id)->first();
                            @endphp

                            <div class="border-t border-gray-100 pt-3 mt-2">
                                @if(!$review)
                                    <!-- Form Beri Rating Bintang 1-5 -->
                                    <form action="{{ route('review.store', $b->id) }}" method="POST" class="p-4 bg-amber-50/80 rounded-2xl border border-amber-200 space-y-3">
                                        @csrf
                                        <div>
                                            <h5 class="text-xs font-black text-amber-900 mb-0.5">⭐ Beri Rating & Ulasan Penjual</h5>
                                            <p class="text-[10px] text-amber-700">Bagaimana kondisi barang preloved dan pengalaman COD kamu?</p>
                                        </div>

                                        <div class="flex flex-col sm:flex-row gap-2">
                                            <select name="rating" required class="bg-white border border-amber-300 text-xs font-bold rounded-xl p-2.5 focus:ring-amber-500">
                                                <option value="5">⭐⭐⭐⭐⭐ (5.0 - Sangat Puas)</option>
                                                <option value="4">⭐⭐⭐⭐ (4.0 - Bagus)</option>
                                                <option value="3">⭐⭐⭐ (3.0 - Cukup)</option>
                                                <option value="2">⭐⭐ (2.0 - Kecewa)</option>
                                                <option value="1">⭐ (1.0 - Sangat Buruk)</option>
                                            </select>
                                            <button type="submit" class="bg-amber-500 hover:bg-amber-600 text-amber-950 font-black text-xs px-4 py-2.5 rounded-xl transition shadow-sm shrink-0">
                                                Kirim Ulasan Bintang
                                            </button>
                                        </div>

                                        <textarea name="comment" rows="2" placeholder="Tulis ulasan singkat kondisi barang..." class="w-full text-xs p-2.5 border border-amber-200 rounded-xl bg-white focus:ring-amber-500"></textarea>
                                    </form>
                                @else
                                    <!-- Kotak Menampilkan Ulasan yang Sudah Dikirim -->
                                    <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 text-xs flex flex-col gap-1">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center gap-1 text-amber-500 font-bold">
                                                @for($s = 1; $s <= 5; $s++)
                                                    {{ $s <= $review->rating ? '★' : '☆' }}
                                                @endfor
                                                <span class="text-slate-700 font-black ml-1 text-xs">({{ $review->rating }}/5.0)</span>
                                            </div>
                                            <span class="text-[10px] text-slate-400 font-medium">Ulasan Anda Terkirim</span>
                                        </div>
                                        @if($review->comment)
                                            <p class="text-slate-600 text-[11px] italic mt-0.5">"{{ $review->comment }}"</p>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @endif

                    </div>
                @empty
                    <div class="bg-white p-12 rounded-2xl text-center border border-dashed border-gray-200">
                        <span class="text-4xl block mb-2">🎟️</span>
                        <p class="text-sm text-gray-500 font-semibold">Kamu belum memiliki riwayat booking barang.</p>
                        <a href="{{ route('home') }}" class="inline-block mt-3 text-xs text-emerald-600 font-bold hover:underline">
                            Cari Barang Preloved Sekarang →
                        </a>
                    </div>
                @endforelse
            </div>

        </div>
    </div>
</x-app-layout>

{{--
    Bayar biaya booking COD lewat QRIS Midtrans. QR dirender di server dari qr_string Midtrans.
    Halaman memeriksa status berkala; begitu webhook settlement/expire masuk, halaman dimuat ulang
    dan diarahkan ke Tiket Saya (lunas) atau kembali ke barang (gagal/kedaluwarsa).
--}}
@php
    $rupiahFee = 'Rp' . number_format($booking->booking_fee, 0, ',', '.');
    // Bantuan uji sandbox; tidak pernah tampil di produksi atau mode simulasi
    $sandboxHelp = config('payment.driver') === 'midtrans' && !config('midtrans.is_production') && filled($booking->payment_qr_url);
@endphp

<x-market-layout title="Bayar Biaya Booking">
    <main class="max-w-md mx-auto px-4 sm:px-6 py-6">
        <div class="bg-white rounded-2xl border border-slate-200 p-5 sm:p-6 shadow-sm space-y-4 text-center">
            <div>
                <h1 class="text-base font-bold text-slate-900">Bayar biaya booking</h1>
                <p class="text-sm text-slate-500 mt-0.5">{{ $booking->product->title }}</p>
            </div>

            <p class="text-sm text-slate-600">Pindai QRIS ini dengan e-wallet atau mobile banking.</p>
            <div class="mx-auto w-56 h-56 p-2 bg-white border border-slate-200 rounded-xl [&>svg]:w-full [&>svg]:h-full" role="img" aria-label="Kode QRIS biaya booking">
                @if($booking->payment_qr_string)
                    {!! \App\Support\QrCode::svg($booking->payment_qr_string, 220) !!}
                @else
                    {{-- Tagihan masih dibuat; kalau gagal, booking dilepas otomatis dan halaman dialihkan --}}
                    <p class="h-full flex items-center justify-center text-sm text-slate-500">Menyiapkan QRIS...</p>
                @endif
            </div>
            <p class="text-2xl font-black text-slate-900 tabular-nums">{{ $rupiahFee }}</p>
            <p class="text-xs text-slate-500">Biaya booking COD · thriftLO</p>

            <p class="text-sm text-slate-700">
                Selesaikan dalam <span id="payCountdown" class="font-semibold tabular-nums" data-expires="{{ $booking->payment_expires_at?->toIso8601String() }}">--:--</span>
            </p>
            <p id="payStatus" class="text-xs text-slate-500" role="status" aria-live="polite">Menunggu pembayaran...</p>

            <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 text-xs text-amber-900 text-left" role="note">
                Biaya booking {{ $rupiahFee }} tidak dapat dikembalikan dan menjadi milik platform. Barang ditahan untukmu selama QR ini berlaku.
            </div>

            @if($sandboxHelp)
                {{-- Status tetap hanya berubah lewat webhook Midtrans; panel ini tidak menandai lunas --}}
                <section class="rounded-lg bg-slate-50 border border-slate-200 p-3 text-left space-y-2.5" aria-labelledby="sandboxHelpTitle">
                    <div>
                        <h2 id="sandboxHelpTitle" class="text-xs font-bold text-slate-800">Cara bayar di sandbox</h2>
                        <p class="text-[11px] text-slate-500">Mode sandbox Midtrans: tidak ada uang sungguhan yang ditarik.</p>
                    </div>
                    <ol class="list-decimal pl-4 space-y-1 text-xs text-slate-700">
                        <li>Tekan <strong>Salin alamat QR</strong>.</li>
                        <li>Tekan <strong>Buka simulator Midtrans</strong>, tempel alamatnya, lalu bayar di sana.</li>
                        <li>Kembali ke halaman ini. Status berubah otomatis setelah Midtrans mengirim notifikasi.</li>
                    </ol>
                    <input id="sandboxQrUrl" type="text" readonly value="{{ $booking->payment_qr_url }}" aria-label="Alamat gambar QR Midtrans" onfocus="this.select()" class="w-full rounded-md border-slate-200 bg-white text-[11px] text-slate-600 font-mono px-2 py-1.5">
                    <div class="flex gap-2">
                        <button type="button" id="sandboxCopy" data-copy="{{ $booking->payment_qr_url }}" class="flex-1 h-9 rounded-lg border border-slate-300 bg-white text-slate-700 hover:bg-slate-100 text-xs font-semibold">Salin alamat QR</button>
                        <a href="{{ config('midtrans.sandbox_simulator_url') }}" target="_blank" rel="noopener noreferrer" class="flex-1 h-9 inline-flex items-center justify-center rounded-lg bg-slate-800 hover:bg-slate-900 text-white text-xs font-semibold">Buka simulator Midtrans</a>
                    </div>
                    <p id="sandboxCopyStatus" class="text-[11px] text-slate-500 min-h-[1em]" role="status" aria-live="polite"></p>
                    <a href="{{ $booking->payment_qr_url }}" target="_blank" rel="noopener noreferrer" class="inline-block text-[11px] font-semibold text-emerald-700 hover:underline">Lihat gambar QR</a>
                </section>
            @elseif(config('payment.driver') === 'midtrans' && !config('midtrans.is_production'))
                <p class="rounded-lg bg-slate-50 border border-slate-200 px-3 py-2 text-[11px] text-slate-500">Mode sandbox Midtrans: bayar lewat QRIS simulator Midtrans, tidak ada uang sungguhan yang ditarik.</p>
            @endif

            <a href="{{ route('bookings.index') }}" class="block w-full h-10 leading-10 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 text-sm font-semibold">Ke Tiket Saya</a>
        </div>
    </main>

    <script>
        (function () {
            const countdown = document.getElementById('payCountdown');
            const expires = new Date(countdown.dataset.expires).getTime();
            const statusUrl = @json(route('booking.payStatus', $booking));

            function tick() {
                const left = Math.max(0, Math.floor((expires - Date.now()) / 1000));
                countdown.textContent = String(Math.floor(left / 60)).padStart(2, '0') + ':' + String(left % 60).padStart(2, '0');
            }
            tick();
            setInterval(tick, 1000);

            async function poll() {
                try {
                    const res = await fetch(statusUrl, { headers: { Accept: 'application/json' } });
                    const data = await res.json();
                    if (data.status !== 'awaiting') {
                        document.getElementById('payStatus').textContent = data.status === 'paid' ? 'Pembayaran diterima, mengalihkan...' : 'Pembayaran tidak selesai, mengalihkan...';
                        window.location.reload();
                        return;
                    }
                } catch (e) {
                    // Jaringan putus sebentar: coba lagi di putaran berikutnya
                }
                setTimeout(poll, 4000);
            }
            setTimeout(poll, 4000);

            // Panel sandbox: salin alamat QR. Clipboard API butuh konteks aman (HTTPS/localhost);
            // selain itu pakai cara lama lewat kotak teks.
            const copyButton = document.getElementById('sandboxCopy');
            if (copyButton) {
                const copyStatus = document.getElementById('sandboxCopyStatus');
                const urlInput = document.getElementById('sandboxQrUrl');
                const label = copyButton.textContent;
                let resetTimer;

                function fallbackCopy() {
                    urlInput.focus();
                    urlInput.select();
                    return document.execCommand('copy');
                }

                copyButton.addEventListener('click', async function () {
                    let copied = false;
                    try {
                        if (navigator.clipboard && window.isSecureContext) {
                            await navigator.clipboard.writeText(copyButton.dataset.copy);
                            copied = true;
                        } else {
                            copied = fallbackCopy();
                        }
                    } catch (e) {
                        try { copied = fallbackCopy(); } catch (e2) { copied = false; }
                    }

                    clearTimeout(resetTimer);
                    copyButton.textContent = copied ? 'Tersalin' : label;
                    copyStatus.textContent = copied ? 'Alamat QR tersalin.' : 'Gagal menyalin, salin manual dari kotak di atas.';
                    resetTimer = setTimeout(function () {
                        copyButton.textContent = label;
                        copyStatus.textContent = '';
                    }, 2000);
                });
            }
        })();
    </script>
</x-market-layout>

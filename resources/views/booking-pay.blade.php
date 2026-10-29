{{--
    Bayar biaya booking COD lewat QRIS Midtrans. QR dirender di server dari qr_string Midtrans.
    Halaman memeriksa status berkala; begitu webhook settlement/expire masuk, halaman dimuat ulang
    dan diarahkan ke Tiket Saya (lunas) atau kembali ke barang (gagal/kedaluwarsa).
--}}
@php
    $rupiahFee = 'Rp' . number_format($booking->booking_fee, 0, ',', '.');
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
                {!! \App\Support\QrCode::svg($booking->payment_qr_string, 220) !!}
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

            @unless(config('midtrans.is_production'))
                <p class="rounded-lg bg-slate-50 border border-slate-200 px-3 py-2 text-[11px] text-slate-500">Mode sandbox Midtrans: bayar lewat QRIS simulator Midtrans, tidak ada uang sungguhan yang ditarik.</p>
            @endunless

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
        })();
    </script>
</x-market-layout>

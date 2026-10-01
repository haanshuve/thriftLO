<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

class SubscriptionController extends Controller
{
    // Halaman paket: kuota gratis, harga langganan, dan sisa masa aktif
    public function show()
    {
        $user = Auth::user();

        if ($user->role !== 'penjual') {
            return redirect()->route('dashboard');
        }

        return view('subscription.show', [
            'user'        => $user,
            'activeCount' => $user->activeProductCount(),
            'plan'        => config('thriftlo.subscription'),
        ]);
    }

    // SIMULASI pembayaran: langsung aktif tanpa payment gateway (nanti diganti Midtrans)
    public function pay()
    {
        $user = Auth::user();
        $plan = config('thriftlo.subscription');

        abort_unless($user->role === 'penjual', 403);

        if ($user->seller_status !== 'verified') {
            return redirect()->route('subscription.show')
                ->with('error', 'Langganan bisa dibeli setelah verifikasi KYC kamu disetujui admin.');
        }

        if (!$plan['simulate_payment']) {
            return redirect()->route('subscription.show')
                ->with('error', 'Pembayaran online belum tersedia. Coba lagi nanti, ya.');
        }

        $user->extendSubscription((int) $plan['days']);

        return redirect()->route('subscription.show')->with('success',
            'Pembayaran berhasil! Langganan aktif sampai ' . $user->subscription_expires_at->locale('id')->translatedFormat('j F Y, H:i') . ' WIB. Sekarang kamu bisa menayangkan produk tanpa batas.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Support\FormToken;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
    public function pay(Request $request)
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

        $formKey = FormToken::validate($request);

        // Formulir bayar yang sama dikirim lagi: jangan tagih/perpanjang dua kali
        if ($paid = SubscriptionPayment::where('idempotency_key', $formKey)->first()) {
            return $this->alreadyPaid($paid);
        }

        try {
            // Kunci baris penjual: dua pembayaran bersamaan sama-sama menambah masa aktif (tidak saling menimpa)
            $payment = DB::transaction(function () use ($user, $plan, $formKey) {
                $seller = User::whereKey($user->id)->lockForUpdate()->first();
                $seller->extendSubscription((int) $plan['days']);
                $user->setRawAttributes($seller->getAttributes(), true);

                return SubscriptionPayment::create([
                    'user_id'         => $seller->id,
                    'idempotency_key' => $formKey,
                    'amount'          => (int) $plan['price'],
                    'days'            => (int) $plan['days'],
                    'paid_at'         => now(),
                    'period_ends_at'  => $seller->subscription_expires_at,
                    'source'          => 'simulasi',
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            // Formulir yang sama selesai lebih dulu di request lain; transaksi ini dibatalkan seluruhnya
            return $this->alreadyPaid(SubscriptionPayment::where('idempotency_key', $formKey)->firstOrFail());
        }

        return redirect()->route('subscription.show')->with('success',
            'Pembayaran berhasil! Langganan aktif sampai ' . $this->formatDate($payment->period_ends_at) . ' WIB. Sekarang kamu bisa menayangkan produk tanpa batas.');
    }

    private function alreadyPaid(SubscriptionPayment $payment)
    {
        if ((int) $payment->user_id !== (int) Auth::id()) {
            return redirect()->route('subscription.show')->with('error', FormToken::REUSED_MESSAGE);
        }

        return redirect()->route('subscription.show')->with('success',
            'Pembayaran ini sudah diproses, jadi tidak ditagih dua kali. Langganan aktif sampai ' . $this->formatDate(Auth::user()->fresh()->subscription_expires_at) . ' WIB.');
    }

    private function formatDate($date): string
    {
        return $date->locale('id')->translatedFormat('j F Y, H:i');
    }
}

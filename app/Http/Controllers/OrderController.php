<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\FormToken;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    // Checkout lewat pengiriman: ongkir diambil dari data produk, bukan dari input pembeli
    public function store(Request $request, Product $product)
    {
        // Admin hanya mengelola platform, tidak ikut berbelanja
        if (Auth::user()->isAdmin()) {
            return redirect()->route('product.show', $product)->with('error', User::ADMIN_CANNOT_BUY_MESSAGE);
        }

        $request->validate([
            'shipping_option'  => 'required|integer|min:0',
            'shipping_address' => 'required|string|max:500',
        ], [
            'shipping_option.required'  => 'Pilih salah satu opsi pengiriman.',
            'shipping_address.required' => 'Alamat pengiriman wajib diisi.',
            'shipping_address.max'      => 'Alamat pengiriman maksimal 500 karakter.',
        ]);
        $formKey = FormToken::validate($request);

        // Kiriman ulang formulir yang sama: jangan buat pesanan kedua, tunjukkan pesanan yang sudah ada
        if ($existing = Order::where('idempotency_key', $formKey)->first()) {
            return $this->orderAlreadyCreated($existing, $product);
        }

        if ((int) $product->user_id === (int) Auth::id()) {
            return back()->with('error', 'Kamu tidak bisa membeli barangmu sendiri.');
        }

        try {
            $order = $this->createOrder($request, $product, $formKey);
        } catch (UniqueConstraintViolationException) {
            // Formulir yang sama selesai lebih dulu di request lain yang datang bersamaan
            $existing = Order::where('idempotency_key', $formKey)->first();

            return $existing
                ? $this->orderAlreadyCreated($existing, $product)
                : back()->with('error', 'Barang sudah tidak tersedia. Cek barang lain yang mirip, yuk!');
        }

        if (!$order) {
            return back()->with('error', 'Yah, barang ini sudah tidak tersedia. Cek barang lain yang mirip, yuk!');
        }

        return redirect()->route('bookings.index')->with('success',
            'Pesanan dibuat! Total Rp' . number_format($order->total_price, 0, ',', '.') . ' via ' . $order->courier . '. Penjual akan segera mengirim barangnya.');
    }

    private function orderAlreadyCreated(Order $existing, Product $product)
    {
        if ((int) $existing->user_id !== (int) Auth::id() || (int) $existing->product_id !== (int) $product->id) {
            return redirect()->route('product.show', $product)->with('error', FormToken::REUSED_MESSAGE);
        }

        return redirect()->route('bookings.index')->with('success', 'Pesanan ini sudah dibuat, jadi tidak dibuat dua kali. Total Rp' . number_format($existing->total_price, 0, ',', '.') . ' via ' . $existing->courier . '.');
    }

    private function createOrder(Request $request, Product $product, string $formKey): ?Order
    {
        // Kunci baris produk supaya dua pembeli (atau booking COD) tidak bisa mengambil barang yang sama bersamaan
        return DB::transaction(function () use ($request, $product, $formKey) {
            $locked = Product::visibleInCatalog()->whereKey($product->id)->lockForUpdate()->first();

            if (!$locked || $locked->status !== 'Available') {
                return null;
            }

            $option = $locked->shippingOptions()[(int) $request->shipping_option] ?? null;
            if (!$option) {
                throw ValidationException::withMessages(['shipping_option' => 'Pilih opsi pengiriman yang tersedia.']);
            }

            $locked->update(['status' => 'Booked']);

            return Order::create([
                'product_id'       => $locked->id,
                'user_id'          => Auth::id(),
                'courier'          => $option['courier'],
                'shipping_cost'    => $option['cost'],
                'item_price'       => $locked->price,
                'total_price'      => $locked->price + $option['cost'],
                'shipping_address' => $request->shipping_address,
                'status'           => Order::AWAITING_SHIPMENT,
                'idempotency_key'  => $formKey,
            ]);
        });
    }

    // Penjual menandai pesanan sudah dikirim (belum pakai nomor resi)
    public function ship(Order $order)
    {
        abort_unless((int) $order->product->user_id === (int) Auth::id(), 403);

        if (!$order->markShipped()) {
            return back()->with('error', 'Pesanan ini sudah ditandai dikirim.');
        }

        return back()->with('success', 'Pesanan "' . $order->product->title . '" ditandai sudah dikirim.');
    }

    // Pembeli konfirmasi barang diterima: dana escrow cair ke penjual (dipotong komisi platform)
    public function confirm(Order $order)
    {
        abort_unless((int) $order->user_id === (int) Auth::id(), 403);

        if (!$order->release(Order::RELEASED_BY_BUYER)) {
            return back()->with('error', $order->status === Order::AWAITING_SHIPMENT
                ? 'Pesanan ini belum dikirim penjual.'
                : 'Pesanan ini sudah selesai sebelumnya.');
        }

        return back()->with('success', 'Terima kasih! Pesanan "' . $order->product->title . '" selesai dan dana sudah diteruskan ke penjual.');
    }
}

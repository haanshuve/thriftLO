<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    // Checkout lewat pengiriman: ongkir diambil dari data produk, bukan dari input pembeli
    public function store(Request $request, Product $product)
    {
        $request->validate([
            'shipping_option'  => 'required|integer|min:0',
            'shipping_address' => 'required|string|max:500',
        ], [
            'shipping_option.required'  => 'Pilih salah satu opsi pengiriman.',
            'shipping_address.required' => 'Alamat pengiriman wajib diisi.',
            'shipping_address.max'      => 'Alamat pengiriman maksimal 500 karakter.',
        ]);

        if ((int) $product->user_id === (int) Auth::id()) {
            return back()->with('error', 'Kamu tidak bisa membeli barangmu sendiri.');
        }

        // Kunci baris produk supaya dua pembeli tidak bisa checkout barang yang sama bersamaan
        $order = DB::transaction(function () use ($request, $product) {
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
            ]);
        });

        if (!$order) {
            return back()->with('error', 'Yah, barang ini sudah tidak tersedia. Cek barang lain yang mirip, yuk!');
        }

        return redirect()->route('bookings.index')->with('success',
            'Pesanan dibuat! Total Rp' . number_format($order->total_price, 0, ',', '.') . ' via ' . $order->courier . '. Penjual akan segera mengirim barangnya.');
    }

    // Penjual menandai pesanan sudah dikirim (belum pakai nomor resi)
    public function ship(Order $order)
    {
        abort_unless((int) $order->product->user_id === (int) Auth::id(), 403);

        if ($order->status !== Order::AWAITING_SHIPMENT) {
            return back()->with('error', 'Pesanan ini sudah ditandai dikirim.');
        }

        $order->update(['status' => Order::SHIPPED, 'shipped_at' => now()]);
        $order->product->update(['status' => 'Sold Out']);

        return back()->with('success', 'Pesanan "' . $order->product->title . '" ditandai sudah dikirim.');
    }
}

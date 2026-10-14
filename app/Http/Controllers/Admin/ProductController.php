<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public const STATUSES = [
        'semua'      => 'Semua',
        'tayang'     => 'Tayang',
        'terjual'    => 'Terjual',
        'diturunkan' => 'Diturunkan',
    ];

    public function index(Request $request)
    {
        $status = array_key_exists($request->query('status'), self::STATUSES) ? $request->query('status') : 'semua';
        $search = trim((string) $request->query('q', ''));

        $products = Product::with('user')
            ->when($status === 'tayang', fn ($q) => $q->active())
            ->when($status === 'terjual', fn ($q) => $q->where('status', 'Sold Out')->whereNull('taken_down_at'))
            ->when($status === 'diturunkan', fn ($q) => $q->whereNotNull('taken_down_at'))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('title', 'like', "%{$search}%")
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('nama_toko', 'like', "%{$search}%"))))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.products', [
            'products'   => $products,
            'status'     => $status,
            'search'     => $search,
            'statuses'   => self::STATUSES,
            'categories' => Category::options(),
        ]);
    }

    // Turunkan produk yang melanggar: hilang dari katalog dan tidak bisa dibeli, alasan terlihat oleh penjual
    public function takeDown(Request $request, Product $product)
    {
        $data = $request->validateWithBag('takedown', [
            'reason' => 'required|string|min:5|max:500',
        ], [
            'reason.required' => 'Tulis alasan penurunan supaya penjual tahu apa yang perlu diperbaiki.',
            'reason.min'      => 'Alasan penurunan minimal 5 karakter.',
            'reason.max'      => 'Alasan penurunan maksimal 500 karakter.',
        ]);

        $product->forceFill([
            'taken_down_at'   => now(),
            'takedown_reason' => trim($data['reason']),
        ])->save();

        return back()->with('success', "Produk \"{$product->title}\" diturunkan dari katalog.");
    }

    public function restore(Product $product)
    {
        $product->forceFill(['taken_down_at' => null, 'takedown_reason' => null])->save();

        return back()->with('success', "Produk \"{$product->title}\" ditayangkan kembali.");
    }
}

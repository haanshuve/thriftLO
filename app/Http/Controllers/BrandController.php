<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    public const SORTS = [
        'terbaru'  => 'Terbaru',
        'termurah' => 'Harga terendah',
        'termahal' => 'Harga tertinggi',
    ];

    // Semua merek aktif beserta jumlah barang yang sedang tayang
    public function index()
    {
        $brands = Brand::active()->withCatalogCount()->orderBy('name')->get();

        return view('brands.index', compact('brands'));
    }

    // Katalog satu merek, dengan filter kategori dan pengurutan
    public function show(Request $request, Brand $brand)
    {
        abort_unless($brand->is_active, 404);

        $categories = Category::options();
        $kategori = array_key_exists((string) $request->query('kategori'), $categories) ? $request->query('kategori') : null;
        $sort = array_key_exists((string) $request->query('urut'), self::SORTS) ? $request->query('urut') : 'terbaru';

        $base = Product::visibleInCatalog()->where('brand_id', $brand->id);

        // Chip kategori hanya untuk kategori yang punya barang merek ini
        $categoryCounts = (clone $base)->selectRaw('kategori, COUNT(*) as total')->groupBy('kategori')->pluck('total', 'kategori');

        $products = (clone $base)->with(['user', 'brand'])
            ->when($kategori, fn ($q) => $q->where('kategori', $kategori))
            ->when($sort === 'termurah', fn ($q) => $q->orderBy('price')->orderByDesc('id'))
            ->when($sort === 'termahal', fn ($q) => $q->orderByDesc('price')->orderByDesc('id'))
            ->when($sort === 'terbaru', fn ($q) => $q->latest()->orderByDesc('id'))
            ->get();

        return view('brands.show', [
            'brand'          => $brand,
            'products'       => $products,
            'categories'     => $categories,
            'categoryCounts' => $categoryCounts,
            'kategori'       => $kategori,
            'sort'           => $sort,
            'sorts'          => self::SORTS,
            'total'          => $categoryCounts->sum(),
        ]);
    }
}

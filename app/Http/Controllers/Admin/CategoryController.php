<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::ordered()->get();

        // Jumlah produk per kategori dalam satu query
        $productCounts = Product::selectRaw('kategori, COUNT(*) as total')->groupBy('kategori')->pluck('total', 'kategori');

        return view('admin.categories', compact('categories', 'productCounts'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, 'createCategory');

        // slug = nilai yang tersimpan di products.kategori; dibuat sekali dan tidak berubah
        $slug = Str::limit(Str::slug($data['label']), 50, '');
        $duplicate = Category::whereRaw('LOWER(slug) = ?', [$slug])
            ->orWhereRaw('LOWER(label) = ?', [Str::lower($data['label'])])
            ->exists();

        if ($slug === '' || $duplicate) {
            return back()->withErrors(['label' => 'Kategori dengan nama ini sudah ada.'], 'createCategory')->withInput();
        }

        Category::create([
            'slug'       => $slug,
            'label'      => $data['label'],
            'icon'       => ($data['icon'] ?? null) ?: '📦',
            'sort_order' => $data['sort_order'] ?? ((int) Category::max('sort_order') + 1),
        ]);

        return back()->with('success', "Kategori \"{$data['label']}\" ditambahkan.");
    }

    public function update(Request $request, Category $category)
    {
        $data = $this->validated($request, 'editCategory');

        $category->update([
            'label'      => $data['label'],
            'icon'       => ($data['icon'] ?? null) ?: $category->icon,
            'sort_order' => $data['sort_order'] ?? $category->sort_order,
        ]);

        return back()->with('success', "Kategori \"{$category->label}\" diperbarui.");
    }

    public function destroy(Category $category)
    {
        // Kategori yang masih dipakai tidak boleh dihapus supaya produk lama tidak kehilangan kategorinya
        $used = Product::where('kategori', $category->slug)->count()
            + ProductRequest::where('kategori', $category->slug)->count();

        if ($used > 0) {
            return back()->with('error', "Kategori \"{$category->label}\" masih dipakai {$used} produk/request, jadi tidak bisa dihapus. Ubah namanya saja bila perlu.");
        }

        if (Category::count() <= 1) {
            return back()->with('error', 'Minimal harus ada satu kategori.');
        }

        $category->delete();

        return back()->with('success', "Kategori \"{$category->label}\" dihapus.");
    }

    private function validated(Request $request, string $bag): array
    {
        return $request->validateWithBag($bag, [
            'label'      => 'required|string|max:50',
            'icon'       => 'nullable|string|max:16',
            'sort_order' => 'nullable|integer|min:0|max:999',
        ], [
            'label.required'     => 'Nama kategori wajib diisi.',
            'label.max'          => 'Nama kategori maksimal 50 karakter.',
            'icon.max'           => 'Ikon terlalu panjang. Pakai satu emoji saja.',
            'sort_order.integer' => 'Urutan harus berupa angka.',
            'sort_order.min'     => 'Urutan tidak boleh negatif.',
            'sort_order.max'     => 'Urutan maksimal 999.',
        ]);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BrandController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        $brands = Brand::withCount('products')
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->ordered()
            ->get();

        return view('admin.brands', compact('brands', 'search'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, 'createBrand');

        if ($this->nameTaken($data['name'])) {
            return back()->withErrors(['name' => 'Merek dengan nama ini sudah ada.'], 'createBrand')->withInput();
        }

        $brand = Brand::create([
            'name'       => $data['name'],
            'is_popular' => $request->boolean('is_popular'),
            'sort_order' => $data['sort_order'] ?? ((int) Brand::max('sort_order') + 1),
            'is_active'  => true,
        ]);

        return back()->with('success', "Merek \"{$brand->name}\" ditambahkan.");
    }

    public function update(Request $request, Brand $brand)
    {
        $data = $this->validated($request, 'editBrand');

        if ($this->nameTaken($data['name'], $brand->id)) {
            return back()->withErrors(['name' => 'Merek dengan nama ini sudah ada.'], 'editBrand')->withInput();
        }

        $brand->update([
            'name'       => $data['name'],
            'is_popular' => $request->boolean('is_popular'),
            'sort_order' => $data['sort_order'] ?? $brand->sort_order,
        ]);

        return back()->with('success', "Merek \"{$brand->name}\" diperbarui.");
    }

    // Aktif/nonaktif: merek nonaktif hilang dari form jual, beranda, dan halaman merek
    public function toggle(Brand $brand)
    {
        $brand->update(['is_active' => !$brand->is_active]);

        return back()->with('success', $brand->is_active
            ? "Merek \"{$brand->name}\" diaktifkan kembali."
            : "Merek \"{$brand->name}\" dinonaktifkan. Produk lama tetap menyimpan mereknya.");
    }

    public function destroy(Brand $brand)
    {
        $used = $brand->products()->count();

        if ($used > 0) {
            return back()->with('error', "Merek \"{$brand->name}\" masih dipakai {$used} produk, jadi tidak bisa dihapus. Nonaktifkan saja.");
        }

        $brand->delete();

        return back()->with('success', "Merek \"{$brand->name}\" dihapus.");
    }

    private function nameTaken(string $name, ?int $ignoreId = null): bool
    {
        return Brand::whereRaw('LOWER(name) = ?', [Str::lower($name)])
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists();
    }

    private function validated(Request $request, string $bag): array
    {
        $request->merge(['name' => trim((string) $request->input('name'))]);

        return $request->validateWithBag($bag, [
            'name'       => 'required|string|max:60',
            'sort_order' => 'nullable|integer|min:0|max:999',
            'is_popular' => ['nullable', Rule::in(['0', '1', 0, 1, true, false, 'on'])],
        ], [
            'name.required'      => 'Nama merek wajib diisi.',
            'name.max'           => 'Nama merek maksimal 60 karakter.',
            'sort_order.integer' => 'Urutan harus berupa angka.',
            'sort_order.min'     => 'Urutan tidak boleh negatif.',
            'sort_order.max'     => 'Urutan maksimal 999.',
        ]);
    }
}

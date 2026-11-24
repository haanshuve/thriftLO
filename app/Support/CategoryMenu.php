<?php

namespace App\Support;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;

/**
 * Data mega menu kategori di header: grup (config thriftlo.category_groups) -> kategori (tabel
 * categories) -> subkategori yang bisa difilter di katalog (merek yang punya barang di kategori itu).
 *
 * Jumlah barang dihitung dengan SATU query agregat (kategori x merek, barang yang tampil di katalog)
 * dan disimpan di cache beberapa menit. URL tidak di-cache, dibuat saat render.
 */
class CategoryMenu
{
    public const CACHE_KEY = 'nav:category-menu:v1';

    // Filter "cara beli" yang ada di katalog (ProductController::COLLECTIONS), tanpa angka:
    // tidak bisa dihitung dengan agregat murah karena bergantung lokasi penjual & opsi kirim.
    public const COLLECTIONS = [
        'cod'   => 'Siap COD di Batam',
        'kirim' => 'Bisa dikirim',
        'video' => 'Dengan Video Proof',
    ];

    public const MAX_BRANDS = 16;

    /**
     * @return list<array{key: string, label: string, categories: list<array{slug: string, label: string, icon: string, count: int, brands: list<array{slug: string, name: string, count: int}>}>}>
     */
    public static function groups(): array
    {
        return Cache::remember(self::CACHE_KEY, (int) config('thriftlo.category_menu_ttl', 300), fn () => self::build());
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private static function build(): array
    {
        $categories = Category::ordered()->get(['slug', 'label']);
        if ($categories->isEmpty()) {
            return [];
        }

        // Satu query agregat: jumlah barang tampil per kategori & merek
        $rows = Product::visibleInCatalog()
            ->selectRaw('kategori, brand_id, COUNT(*) as n')
            ->groupBy('kategori', 'brand_id')
            ->toBase()->get();

        $brandIds = $rows->pluck('brand_id')->filter()->unique();
        // Hanya merek aktif: filter ?merek= di katalog juga hanya mengenali merek aktif
        $brands = $brandIds->isEmpty() ? collect() : Brand::active()->whereIn('id', $brandIds)->get(['id', 'name', 'slug'])->keyBy('id');

        $byCategory = $rows->groupBy('kategori');
        $icons = (array) config('thriftlo.category_icons', []);

        $entry = function (Category $c) use ($byCategory, $brands, $icons) {
            $catRows = $byCategory->get($c->slug, collect());

            return [
                'slug'   => $c->slug,
                'label'  => $c->label,
                'icon'   => $icons[$c->slug] ?? 'box',
                'count'  => (int) $catRows->sum('n'),
                'brands' => $catRows
                    ->filter(fn ($r) => $r->brand_id && $brands->has($r->brand_id))
                    ->map(fn ($r) => ['slug' => $brands[$r->brand_id]->slug, 'name' => $brands[$r->brand_id]->name, 'count' => (int) $r->n])
                    ->sortBy([['count', 'desc'], ['name', 'asc']])
                    ->take(self::MAX_BRANDS)->values()->all(),
            ];
        };

        $bySlug = $categories->keyBy('slug');
        $placed = [];
        $groups = [];

        foreach ((array) config('thriftlo.category_groups', []) as $key => $group) {
            $items = [];
            foreach ((array) ($group['categories'] ?? []) as $slug) {
                // Slug di config yang tidak ada di tabel categories diabaikan
                if ($bySlug->has($slug) && !isset($placed[$slug])) {
                    $items[] = $entry($bySlug[$slug]);
                    $placed[$slug] = true;
                }
            }
            $groups[$key] = ['key' => (string) $key, 'label' => (string) ($group['label'] ?? $key), 'categories' => $items];
        }

        // Kategori baru dari admin yang belum dipetakan: masuk grup terakhir (atau grup "Lainnya" baru)
        $unplaced = $categories->reject(fn (Category $c) => isset($placed[$c->slug]))->map($entry)->values()->all();
        if ($unplaced) {
            $lastKey = array_key_last($groups) ?? 'lainnya';
            $groups[$lastKey] ??= ['key' => $lastKey, 'label' => 'Lainnya', 'categories' => []];
            array_push($groups[$lastKey]['categories'], ...$unplaced);
        }

        // Grup tanpa kategori yang ada tidak ditampilkan
        return array_values(array_filter($groups, fn ($g) => $g['categories'] !== []));
    }
}

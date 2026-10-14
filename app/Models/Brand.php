<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Brand extends Model
{
    protected $fillable = ['name', 'slug', 'is_popular', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_popular' => 'boolean',
            'is_active'  => 'boolean',
        ];
    }

    // Slug dibuat otomatis dari nama (mis. "Levi's" -> "levis") dan selalu unik
    protected static function booted(): void
    {
        static::saving(function (Brand $brand) {
            if (!$brand->slug || $brand->isDirty('name')) {
                $brand->slug = static::uniqueSlug($brand->name, $brand->id);
            }
        });
    }

    public static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::limit(Str::slug($name), 60, '') ?: 'merek';
        $slug = $base;

        for ($i = 2; static::where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    // Jumlah produk yang tampil di katalog per merek, dihitung dalam satu query (withCount)
    public function scopeWithCatalogCount(Builder $query): Builder
    {
        return $query->withCount(['products as catalog_count' => fn ($q) => $q->visibleInCatalog()]);
    }

    // Merek aktif dengan nama yang sama persis (tanpa beda huruf besar/kecil), untuk input "Merek lain"
    public static function findActiveByName(string $name): ?self
    {
        return static::active()->whereRaw('LOWER(name) = ?', [Str::lower(trim($name))])->first();
    }
}

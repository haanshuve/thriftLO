<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ['slug', 'label', 'icon', 'sort_order'];

    // Mega menu header menyimpan daftar kategori di cache: segarkan saat admin mengubahnya
    protected static function booted(): void
    {
        static::saved(fn () => \App\Support\CategoryMenu::forget());
        static::deleted(fn () => \App\Support\CategoryMenu::forget());
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Kategori dalam format yang dipakai view & validasi:
     * [slug => ['label' => ..., 'icon' => ...]], urut sesuai sort_order.
     *
     * @return array<string, array{label: string, icon: string}>
     */
    public static function options(): array
    {
        return static::ordered()->get()
            ->mapWithKeys(fn (Category $c) => [$c->slug => ['label' => $c->label, 'icon' => $c->icon]])
            ->all();
    }

    public static function slugs(): array
    {
        return static::ordered()->pluck('slug')->all();
    }

    public function productCount(): int
    {
        return Product::where('kategori', $this->slug)->count();
    }
}

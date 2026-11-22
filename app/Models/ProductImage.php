<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    protected $fillable = ['product_id', 'path', 'urutan'];

    protected function casts(): array
    {
        return ['urutan' => 'integer'];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function url(): string
    {
        return Product::publicImageUrl($this->path);
    }
}

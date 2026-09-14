<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'nama_barang',
        'title',
        'mode_jual',
        'kategori',
        'harga',
        'price',
        'grade',
        'image_url',
        'image_path',
        'video_proof_url',
        'video_proof',
        'deskripsi',
        'description',
        'status',
    ];

    // Ototatis isi kolom image_path jika kosong agar tidak error database
    protected static function booted()
    {
        static::saving(function ($product) {
            if (empty($product->image_path) && !empty($product->image_url)) {
                $product->image_path = $product->image_url;
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    // Tambahkan baris ini agar kolom-kolom tabel bisa disimpan
    protected $fillable = [
        'product_id',
        'user_id',
        'qr_token',
        'cod_location',
        'cod_schedule',
        'status_cod',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

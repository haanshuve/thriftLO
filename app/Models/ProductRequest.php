<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'nama_barang',
        'kategori',
        'budget_maksimal',
        'lokasi_cod',
        'deskripsi',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

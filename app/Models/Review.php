<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'order_id',
        'product_id',
        'seller_id',
        'buyer_id',
        'rating',
        'comment',
    ];

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    // Nama pengulas yang tampil publik disamarkan: "Raihan Hanafi" -> "R****n"
    public function buyerDisplayName(): string
    {
        $first = (string) str($this->buyer?->name ?? 'Pembeli')->trim()->before(' ');
        $len = mb_strlen($first);

        return $len <= 2 ? $first . '***' : mb_substr($first, 0, 1) . str_repeat('*', $len - 2) . mb_substr($first, -1);
    }
}

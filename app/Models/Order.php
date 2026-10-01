<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    public const AWAITING_SHIPMENT = 'Awaiting Shipment';
    public const SHIPPED = 'Shipped';

    public const STATUS_LABELS = [
        self::AWAITING_SHIPMENT => 'Menunggu Dikirim',
        self::SHIPPED           => 'Sudah Dikirim',
    ];

    protected $fillable = [
        'product_id',
        'user_id',
        'courier',
        'shipping_cost',
        'item_price',
        'total_price',
        'shipping_address',
        'status',
        'shipped_at',
    ];

    protected function casts(): array
    {
        return [
            'shipped_at' => 'datetime',
        ];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // Pembeli
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Satu pembayaran langganan penjual (saat ini simulasi). idempotency_key mencegah bayar dobel.
class SubscriptionPayment extends Model
{
    protected $fillable = ['user_id', 'idempotency_key', 'amount', 'days', 'paid_at', 'period_ends_at', 'source'];

    protected function casts(): array
    {
        return [
            'amount'         => 'integer',
            'days'           => 'integer',
            'paid_at'        => 'datetime',
            'period_ends_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

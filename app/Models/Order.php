<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class Order extends Model
{
    public const AWAITING_SHIPMENT = 'Awaiting Shipment';
    public const SHIPPED = 'Shipped';
    public const COMPLETED = 'Completed';

    public const RELEASED_BY_BUYER = 'buyer';
    public const RELEASED_AUTOMATICALLY = 'auto';

    public const STATUS_LABELS = [
        self::AWAITING_SHIPMENT => 'Menunggu Dikirim',
        self::SHIPPED           => 'Sudah Dikirim',
        self::COMPLETED         => 'Selesai',
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
            'shipped_at'     => 'datetime',
            'released_at'    => 'datetime',
            'biaya_platform' => 'integer',
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

    public function isReleased(): bool
    {
        return $this->released_at !== null;
    }

    // Kapan dana otomatis cair kalau pembeli tidak konfirmasi
    public function autoReleaseAt(): ?Carbon
    {
        return $this->shipped_at?->copy()->addDays((int) config('thriftlo.escrow.auto_release_days'));
    }

    // Dana bersih yang diterima penjual: total dibayar pembeli dikurangi komisi platform
    public function sellerPayout(): int
    {
        return (int) $this->total_price - $this->biaya_platform;
    }

    // Komisi tetap per transaksi Kirim, tidak pernah melebihi total transaksi
    public static function platformFeeFor(int $total): int
    {
        return min((int) config('thriftlo.escrow.platform_fee'), $total);
    }

    // Cairkan dana ke penjual sambil memotong komisi. Baris dikunci supaya konfirmasi pembeli
    // dan auto-release yang berjalan bersamaan tidak mencairkan dua kali.
    public function release(string $method): bool
    {
        return DB::transaction(function () use ($method) {
            $locked = static::whereKey($this->id)->lockForUpdate()->first();

            if (!$locked || $locked->status !== self::SHIPPED || $locked->isReleased()) {
                return false;
            }

            $locked->forceFill([
                'status'         => self::COMPLETED,
                'released_at'    => now(),
                'release_method' => $method,
                'biaya_platform' => self::platformFeeFor((int) $locked->total_price),
            ])->save();

            $this->setRawAttributes($locked->getAttributes(), true);

            return true;
        });
    }

    public function scopeDueForAutoRelease(Builder $query): Builder
    {
        return $query->where('status', self::SHIPPED)
            ->whereNull('released_at')
            ->where('shipped_at', '<=', now()->subDays((int) config('thriftlo.escrow.auto_release_days')));
    }

    // Cairkan semua pesanan yang sudah lewat batas konfirmasi. Dipanggil scheduler dan juga
    // saat halaman pesanan dibuka, supaya tetap akurat walau worker scheduler tidak jalan.
    public static function releaseOverdue(): int
    {
        return static::dueForAutoRelease()->get()
            ->filter(fn (Order $order) => $order->release(self::RELEASED_AUTOMATICALLY))
            ->count();
    }
}

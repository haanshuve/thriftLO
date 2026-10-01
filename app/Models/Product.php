<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'kategori',
        'mode_jual',
        'grade',
        'price',
        'image_path',
        'image_url',
        'video_proof',
        'shipping_options',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'shipping_options' => 'array',
        ];
    }

    // COD (ketemu langsung) hanya untuk penjual yang lapaknya di Batam
    public function supportsCod(): bool
    {
        return (bool) $this->user?->isInBatam();
    }

    /** @return list<array{courier: string, cost: int}> */
    public function shippingOptions(): array
    {
        return $this->shipping_options ?? [];
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    // Ototatis isi kolom image_path jika kosong agar tidak error database
    protected static function booted()
    {
        static::saving(function ($product) {
            if (empty($product->image_path) && !empty($product->image_url)) {
                $product->image_path = $product->image_url;
            }
        });
    }

    // Status produk yang masih tayang di katalog dan dihitung ke kuota gratis
    public const ACTIVE_STATUSES = ['Available', 'Booked'];

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', self::ACTIVE_STATUSES);
    }

    /**
     * Produk yang tampil di katalog: semua produk aktif penjual berlangganan, atau
     * hanya N produk aktif pertama (urut upload) milik penjual tanpa langganan.
     * Sisanya disembunyikan, bukan dihapus, dan tampil lagi begitu penjual berlangganan.
     */
    public function scopeVisibleInCatalog(Builder $query): Builder
    {
        $limit = (int) config('thriftlo.subscription.free_product_limit');

        return $query->active()->where(function (Builder $q) use ($limit) {
            $q->whereHas('user', fn (Builder $u) => $u->where('subscription_expires_at', '>', now()))
                ->orWhereRaw(
                    '(select count(*) from products as earlier where earlier.user_id = products.user_id and earlier.status in (?, ?) and earlier.id < products.id) < ?',
                    [...self::ACTIVE_STATUSES, $limit]
                );
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

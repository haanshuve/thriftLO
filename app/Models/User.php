<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
  protected $fillable = [
    'name',
    'email',
    'google_id',
    'password',
    'role',
    'phone_number',
    'nama_toko',
    'lokasi_lapak',
    'ktp_number',
    'ktp_photo_path',
    'selfie_path',
    'seller_status',
];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'subscription_expires_at' => 'datetime',
        ];
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    // Lokasi lapak dari registrasi KYC; COD hanya tersedia kalau mengandung kata "Batam"
    public function isInBatam(): bool
    {
        return str_contains(mb_strtolower((string) $this->lokasi_lapak), 'batam');
    }

    public function hasActiveSubscription(): bool
    {
        return $this->subscription_expires_at !== null && $this->subscription_expires_at->isFuture();
    }

    // Sisa hari langganan, dibulatkan ke atas (sisa 2 jam = 1 hari)
    public function subscriptionDaysLeft(): int
    {
        return $this->hasActiveSubscription()
            ? (int) ceil(now()->diffInDays($this->subscription_expires_at))
            : 0;
    }

    // Perpanjang dari tanggal habis kalau masih aktif, supaya sisa hari tidak hangus
    public function extendSubscription(int $days): void
    {
        $from = $this->hasActiveSubscription() ? $this->subscription_expires_at : now();

        $this->forceFill(['subscription_expires_at' => $from->copy()->addDays($days)])->save();
    }

    public function activeProductCount(): int
    {
        return $this->products()->active()->count();
    }

    public function canListMoreProducts(): bool
    {
        return $this->hasActiveSubscription()
            || $this->activeProductCount() < (int) config('thriftlo.subscription.free_product_limit');
    }
}

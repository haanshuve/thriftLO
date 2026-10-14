<?php

namespace App\Models;

use App\Support\QrCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Booking extends Model
{
    use HasFactory;

    // Booking yang masih berjalan (token QR masih berlaku)
    public const ACTIVE_STATUSES = ['Pending', 'Confirmed'];

    // Status biaya booking
    public const FEE_PAID = 'paid';
    public const FEE_FORFEITED = 'forfeited';
    public const FEE_REFUNDED = 'refunded';

    public const CANCELLED_BY_BUYER = 'buyer';
    public const CANCELLED_BY_SELLER = 'seller';

    public const BUYER_CANCEL_REASONS = [
        'berubah_pikiran'    => 'Berubah pikiran',
        'barang_lain'        => 'Menemukan barang lain',
        'tidak_bisa_bertemu' => 'Tidak bisa bertemu',
        'lainnya'            => 'Lainnya',
    ];

    public const SELLER_CANCEL_REASONS = [
        'sudah_terjual'      => 'Barang sudah terjual',
        'barang_rusak'       => 'Barang rusak',
        'tidak_bisa_bertemu' => 'Tidak bisa bertemu',
        'lainnya'            => 'Lainnya',
    ];

    protected $fillable = [
        'product_id',
        'user_id',
        'qr_token',
        'cod_location',
        'cod_schedule',
        'status_cod',
        'booking_fee',
        'fee_status',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'booking_fee'  => 'integer',
            'paid_at'      => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function review()
    {
        return $this->hasOne(Review::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status_cod, self::ACTIVE_STATUSES, true);
    }

    public function statusLabel(): string
    {
        return match ($this->status_cod) {
            'Pending'   => 'Menunggu COD',
            'Confirmed' => 'Dikonfirmasi penjual',
            'Completed' => 'Selesai',
            'Cancelled' => match ($this->cancelled_by) {
                self::CANCELLED_BY_BUYER  => 'Dibatalkan oleh pembeli',
                self::CANCELLED_BY_SELLER => 'Dibatalkan oleh penjual',
                default                   => 'Dibatalkan',
            },
            default => (string) $this->status_cod,
        };
    }

    public function feeStatusLabel(): ?string
    {
        return match ($this->fee_status) {
            self::FEE_PAID      => 'Dibayar',
            self::FEE_FORFEITED => 'Tidak dikembalikan',
            self::FEE_REFUNDED  => 'Dikembalikan ke pembeli',
            default             => null,
        };
    }

    public function cancelReasonLabel(): ?string
    {
        if (!$this->cancel_reason) {
            return null;
        }

        $reasons = $this->cancelled_by === self::CANCELLED_BY_SELLER ? self::SELLER_CANCEL_REASONS : self::BUYER_CANCEL_REASONS;

        return $reasons[$this->cancel_reason] ?? null;
    }

    /**
     * Pembeli membatalkan: biaya booking hangus (komitmen), barang kembali tayang.
     * Mengembalikan false kalau booking sudah tidak aktif (selesai/dibatalkan).
     */
    public function cancelByBuyer(?string $reason): bool
    {
        return $this->cancel(self::CANCELLED_BY_BUYER, $reason, self::FEE_FORFEITED, relistProduct: true);
    }

    /**
     * Penjual membatalkan: biaya booking dikembalikan ke pembeli. Barang tayang lagi
     * atau diturunkan dari katalog sesuai pilihan penjual.
     */
    public function cancelBySeller(string $reason, bool $relistProduct): bool
    {
        return $this->cancel(self::CANCELLED_BY_SELLER, $reason, self::FEE_REFUNDED, $relistProduct);
    }

    private function cancel(string $by, ?string $reason, string $feeStatus, bool $relistProduct): bool
    {
        return DB::transaction(function () use ($by, $reason, $feeStatus, $relistProduct) {
            // Kunci baris supaya pembatalan ganda atau bentrok dengan verifikasi QR tidak terjadi
            $locked = self::whereKey($this->id)->lockForUpdate()->first();

            if (!$locked || !$locked->isActive()) {
                return false;
            }

            $locked->forceFill([
                'status_cod'    => 'Cancelled',
                'cancelled_by'  => $by,
                'cancel_reason' => $reason,
                'cancelled_at'  => now(),
                // Booking lama tanpa biaya tidak punya status biaya
                'fee_status'    => $locked->booking_fee > 0 ? $feeStatus : null,
            ])->save();

            $product = Product::whereKey($locked->product_id)->lockForUpdate()->first();
            if ($product) {
                if (!$relistProduct) {
                    $product->update(['status' => 'Sold Out']);
                } elseif ($product->status === 'Booked') {
                    $product->update(['status' => 'Available']);
                }
            }

            $this->setRawAttributes($locked->getAttributes(), true);

            return true;
        });
    }

    // QR token COD sebagai SVG inline
    public function qrCodeSvg(int $size = 200): string
    {
        return QrCode::svg($this->qr_token, $size);
    }
}

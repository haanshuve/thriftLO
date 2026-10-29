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

    // Mode Midtrans: booking sudah dibuat dan barang ditahan, biaya booking belum lunas.
    // Bukan status aktif: belum bisa dibatalkan atau diverifikasi QR sampai webhook settlement.
    public const AWAITING_PAYMENT = 'AwaitingPayment';

    // Status biaya booking
    public const FEE_PENDING = 'pending';
    public const FEE_PAID = 'paid';
    // Biaya booking selalu menjadi milik platform setelah dibatalkan, siapa pun yang membatalkan
    public const FEE_FORFEITED = 'forfeited';
    // Hanya untuk data lama di database; tidak ada kode yang membuat status ini lagi
    public const FEE_REFUNDED = 'refunded';

    public const CANCELLED_BY_BUYER = 'buyer';
    public const CANCELLED_BY_SELLER = 'seller';
    // Dibatalkan otomatis (mis. migrasi membersihkan booking aktif ganda); cancel_reason berisi catatannya
    public const CANCELLED_BY_SYSTEM = 'system';

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
        'idempotency_key',
        'payment_order_id',
        'payment_status',
        'payment_qr_string',
        'payment_expires_at',
    ];

    // Catatan pembatalan otomatis saat pembayaran QRIS gagal, per transaction_status Midtrans
    public const PAYMENT_FAILURE_NOTES = [
        'expire' => 'Pembayaran QRIS kedaluwarsa',
        'cancel' => 'Pembayaran QRIS dibatalkan',
        'deny'   => 'Pembayaran QRIS ditolak',
    ];

    /*
     * active_product_id = product_id selama booking aktif atau menunggu pembayaran, NULL setelah selesai/dibatalkan.
     * Unique index di kolom ini menjamin satu booking aktif per produk di level database,
     * jadi dua booking bersamaan untuk barang yang sama tidak mungkin sama-sama tersimpan.
     */
    protected static function booted(): void
    {
        static::saving(function (Booking $booking) {
            $booking->active_product_id = $booking->isActive() || $booking->isAwaitingPayment() ? $booking->product_id : null;
        });
    }

    protected function casts(): array
    {
        return [
            'booking_fee'        => 'integer',
            'paid_at'            => 'datetime',
            'cancelled_at'       => 'datetime',
            'payment_expires_at' => 'datetime',
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

    public function isAwaitingPayment(): bool
    {
        return $this->status_cod === self::AWAITING_PAYMENT;
    }

    public function statusLabel(): string
    {
        return match ($this->status_cod) {
            self::AWAITING_PAYMENT => 'Menunggu pembayaran',
            'Pending'   => 'Menunggu COD',
            'Confirmed' => 'Dikonfirmasi penjual',
            'Completed' => 'Selesai',
            'Cancelled' => match ($this->cancelled_by) {
                self::CANCELLED_BY_BUYER  => 'Dibatalkan oleh pembeli',
                self::CANCELLED_BY_SELLER => 'Dibatalkan oleh penjual',
                self::CANCELLED_BY_SYSTEM => 'Dibatalkan otomatis',
                default                   => 'Dibatalkan',
            },
            default => (string) $this->status_cod,
        };
    }

    public function feeStatusLabel(): ?string
    {
        return match ($this->fee_status) {
            self::FEE_PENDING   => 'Menunggu pembayaran',
            self::FEE_PAID      => 'Dibayar',
            self::FEE_FORFEITED => 'Milik platform',
            self::FEE_REFUNDED  => 'Dikembalikan ke pembeli',
            default             => null,
        };
    }

    public function cancelReasonLabel(): ?string
    {
        if (!$this->cancel_reason) {
            return null;
        }

        // Pembatalan otomatis menyimpan catatan apa adanya, bukan kode alasan
        if ($this->cancelled_by === self::CANCELLED_BY_SYSTEM) {
            return $this->cancel_reason;
        }

        $reasons = $this->cancelled_by === self::CANCELLED_BY_SELLER ? self::SELLER_CANCEL_REASONS : self::BUYER_CANCEL_REASONS;

        return $reasons[$this->cancel_reason] ?? null;
    }

    /**
     * Pembeli membatalkan: biaya booking menjadi milik platform, barang tayang lagi.
     * Mengembalikan false kalau booking sudah tidak aktif (selesai/dibatalkan).
     */
    public function cancelByBuyer(?string $reason): bool
    {
        return $this->cancel(self::CANCELLED_BY_BUYER, $reason, self::FEE_FORFEITED, relistProduct: true);
    }

    /**
     * Penjual membatalkan: biaya booking tetap menjadi milik platform, sama seperti pembatalan
     * pembeli. Barang tayang lagi atau diturunkan dari katalog sesuai pilihan penjual.
     */
    public function cancelBySeller(string $reason, bool $relistProduct): bool
    {
        return $this->cancel(self::CANCELLED_BY_SELLER, $reason, self::FEE_FORFEITED, $relistProduct);
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

    /**
     * Webhook settlement: biaya booking lunas, booking menjadi aktif (Menunggu COD).
     * Idempotent: hanya booking yang masih menunggu pembayaran yang berubah.
     * Settlement yang terlambat (booking sudah dilepas karena kedaluwarsa) tetap dicatat sebagai
     * milik platform tanpa mengaktifkan lagi booking-nya, karena barangnya mungkin sudah dipesan orang lain.
     *
     * @return string paid | late_forfeited | ignored
     */
    public function markFeePaid(): string
    {
        return DB::transaction(function () {
            $locked = self::whereKey($this->id)->lockForUpdate()->first();
            $result = 'ignored';

            if ($locked?->isAwaitingPayment()) {
                $locked->forceFill([
                    'status_cod'     => 'Pending',
                    'fee_status'     => self::FEE_PAID,
                    'paid_at'        => now(),
                    'payment_status' => 'settlement',
                ])->save();
                $result = 'paid';
            } elseif ($locked && $locked->status_cod === 'Cancelled' && $locked->fee_status === null && $locked->booking_fee > 0) {
                $locked->forceFill([
                    'fee_status'     => self::FEE_FORFEITED,
                    'paid_at'        => now(),
                    'payment_status' => 'settlement',
                ])->save();
                $result = 'late_forfeited';
            }

            if ($locked) {
                $this->setRawAttributes($locked->getAttributes(), true);
            }

            return $result;
        });
    }

    /**
     * Pembayaran gagal (expire/cancel/deny) atau lewat batas waktu: booking dibatalkan sistem dan
     * barang tayang lagi. Uang tidak pernah masuk, jadi tidak ada biaya yang menjadi milik platform.
     * Idempotent: mengembalikan false kalau booking sudah tidak menunggu pembayaran.
     */
    public function failPayment(string $paymentStatus): bool
    {
        return DB::transaction(function () use ($paymentStatus) {
            $locked = self::whereKey($this->id)->lockForUpdate()->first();

            if (!$locked?->isAwaitingPayment()) {
                return false;
            }

            $locked->forceFill([
                'status_cod'     => 'Cancelled',
                'cancelled_by'   => self::CANCELLED_BY_SYSTEM,
                'cancel_reason'  => self::PAYMENT_FAILURE_NOTES[$paymentStatus] ?? self::PAYMENT_FAILURE_NOTES['expire'],
                'cancelled_at'   => now(),
                'fee_status'     => null,
                'payment_status' => $paymentStatus,
            ])->save();

            $product = Product::whereKey($locked->product_id)->lockForUpdate()->first();
            if ($product?->status === 'Booked') {
                $product->update(['status' => 'Available']);
            }

            $this->setRawAttributes($locked->getAttributes(), true);

            return true;
        });
    }

    // Cadangan kalau webhook expire tidak sampai: lepas booking yang lewat batas bayar
    public static function releaseExpiredPayments(): int
    {
        return static::where('status_cod', self::AWAITING_PAYMENT)
            ->where('payment_expires_at', '<', now())
            ->get()
            ->filter(fn (Booking $booking) => $booking->failPayment('expire'))
            ->count();
    }

    // QR token COD sebagai SVG inline
    public function qrCodeSvg(int $size = 200): string
    {
        return QrCode::svg($this->qr_token, $size);
    }
}

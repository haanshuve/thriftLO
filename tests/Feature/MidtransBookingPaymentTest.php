<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Biaya booking lewat payment gateway: booking menunggu pembayaran menahan barang,
 * lunas lewat settlement, dilepas saat gagal/kedaluwarsa. Fee yang lunas selalu milik platform.
 */
class MidtransBookingPaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;
    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seller = User::factory()->create(['role' => 'penjual', 'seller_status' => 'verified', 'nama_toko' => 'Lapak Nagoya']);
        $this->buyer = User::factory()->create(['role' => 'pembeli']);
    }

    private function awaitingBooking(array $overrides = []): Booking
    {
        $product = Product::create(['user_id' => $this->seller->id, 'title' => 'Jaket Denim', 'price' => 50000, 'image_url' => 'products/x.jpg', 'status' => 'Booked']);

        return Booking::create($overrides + [
            'product_id' => $product->id, 'user_id' => $this->buyer->id, 'qr_token' => 'TL-AWAIT001',
            'cod_location' => 'Nagoya Hill', 'cod_schedule' => now()->addDay(), 'status_cod' => Booking::AWAITING_PAYMENT,
            'booking_fee' => 2000, 'fee_status' => Booking::FEE_PENDING,
            'payment_order_id' => 'TL-BKG-1-TEST01', 'payment_status' => 'pending',
            'payment_qr_string' => 'qris-palsu', 'payment_expires_at' => now()->addMinutes(15),
        ]);
    }

    public function test_awaiting_payment_holds_product_but_is_not_active(): void
    {
        $booking = $this->awaitingBooking();

        $this->assertFalse($booking->isActive());
        $this->assertSame($booking->product_id, $booking->active_product_id);
        $this->assertSame('Menunggu pembayaran', $booking->statusLabel());

        // Booking kedua untuk barang yang sama ditolak database selama pembayaran berjalan
        $this->expectException(UniqueConstraintViolationException::class);
        Booking::create([
            'product_id' => $booking->product_id, 'user_id' => $this->buyer->id, 'qr_token' => 'TL-AWAIT002',
            'cod_location' => 'Nagoya', 'cod_schedule' => now()->addDay(), 'status_cod' => 'Pending',
        ]);
    }

    public function test_settlement_marks_fee_paid_once(): void
    {
        $booking = $this->awaitingBooking();

        $this->assertSame('paid', $booking->markFeePaid());
        $paidAt = $booking->paid_at;
        $this->travel(5)->minutes();
        $this->assertSame('ignored', $booking->fresh()->markFeePaid());

        $booking->refresh();
        $this->assertSame('Pending', $booking->status_cod);
        $this->assertSame(Booking::FEE_PAID, $booking->fee_status);
        $this->assertEquals($paidAt, $booking->paid_at);
        $this->assertTrue($booking->isActive());
    }

    public function test_failed_payment_releases_product_without_platform_fee(): void
    {
        $booking = $this->awaitingBooking();

        $this->assertTrue($booking->failPayment('deny'));
        $this->assertFalse($booking->fresh()->failPayment('expire'));

        $booking->refresh();
        $this->assertSame('Cancelled', $booking->status_cod);
        $this->assertSame(Booking::CANCELLED_BY_SYSTEM, $booking->cancelled_by);
        $this->assertSame('Pembayaran QRIS ditolak', $booking->cancelReasonLabel());
        $this->assertNull($booking->fee_status);
        $this->assertNull($booking->active_product_id);
        $this->assertSame('Available', $booking->product->status);
    }

    public function test_expired_payments_are_released_lazily(): void
    {
        $booking = $this->awaitingBooking(['payment_expires_at' => now()->subMinute()]);

        $this->assertSame(1, Booking::releaseExpiredPayments());
        $this->assertSame(0, Booking::releaseExpiredPayments());
        $this->assertSame('Pembayaran QRIS kedaluwarsa', $booking->fresh()->cancel_reason);
    }

    // Proses berhenti saat memanggil Midtrans: booking tersimpan tanpa QR dan tanpa batas bayar
    public function test_booking_without_qr_or_expiry_is_released_after_grace_period(): void
    {
        $booking = $this->awaitingBooking(['payment_qr_string' => null, 'payment_expires_at' => null]);

        $this->assertSame(0, Booking::releaseExpiredPayments(), 'Tagihan yang mungkin masih dibuat tidak boleh dilepas');
        $this->assertSame(Booking::AWAITING_PAYMENT, $booking->fresh()->status_cod);

        $this->travel(Booking::QR_CREATION_GRACE_SECONDS + 1)->seconds();

        $this->assertSame(1, Booking::releaseExpiredPayments());
        $this->assertSame(0, Booking::releaseExpiredPayments());
        $booking->refresh();
        $this->assertSame('Cancelled', $booking->status_cod);
        $this->assertSame('Tagihan QRIS gagal dibuat', $booking->cancel_reason);
        $this->assertNull($booking->fee_status);
        $this->assertNull($booking->active_product_id);
        $this->assertSame('Available', $booking->product->status);
    }

    public function test_payment_page_without_qr_does_not_crash_and_releases_after_grace_period(): void
    {
        $booking = $this->awaitingBooking(['payment_qr_string' => null, 'payment_expires_at' => null]);

        $this->actingAs($this->buyer)->get(route('booking.pay', $booking))
            ->assertOk()->assertSee('Menyiapkan QRIS...');

        $this->travel(Booking::QR_CREATION_GRACE_SECONDS + 1)->seconds();

        $this->actingAs($this->buyer)->get(route('booking.pay', $booking))
            ->assertRedirect(route('product.show', $booking->product_id));
        $this->assertSame('Available', $booking->product->fresh()->status);
    }

    public function test_late_settlement_after_release_is_platform_owned_without_reactivating(): void
    {
        $booking = $this->awaitingBooking();
        $booking->failPayment('expire');

        $this->assertSame('late_forfeited', $booking->fresh()->markFeePaid());
        $this->assertSame('ignored', $booking->fresh()->markFeePaid());

        $booking->refresh();
        $this->assertSame('Cancelled', $booking->status_cod);
        $this->assertSame(Booking::FEE_FORFEITED, $booking->fee_status);
        $this->assertSame('Available', $booking->product->status);
    }

    public function test_seller_cannot_verify_unpaid_token(): void
    {
        $booking = $this->awaitingBooking();

        $this->actingAs($this->seller)->post(route('booking.verify'), ['qr_code_token' => $booking->qr_token])
            ->assertSessionHas('error', 'Biaya booking untuk token ini belum lunas, jadi token belum berlaku.');

        $this->assertSame(Booking::AWAITING_PAYMENT, $booking->fresh()->status_cod);
        $this->assertSame('Booked', $booking->product->fresh()->status);
    }

    public function test_pending_fee_is_not_counted_in_admin_stats(): void
    {
        $this->awaitingBooking();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('stats', fn ($stats) => $stats['bookingFees'] === 0);
    }

    // Booking ikut terhapus bersama barangnya, jadi settlement sesudahnya akan jatuh ke order tak dikenal
    public function test_seller_cannot_delete_product_while_payment_is_awaited(): void
    {
        $booking = $this->awaitingBooking();

        $this->actingAs($this->seller)->from('/dashboard')->delete(route('product.destroy', $booking->product_id))
            ->assertRedirect('/dashboard')
            ->assertSessionHas('error');

        $this->assertModelExists($booking->product);
        $this->assertSame('paid', $booking->fresh()->markFeePaid());
    }

    public function test_seller_cannot_delete_product_with_active_booking(): void
    {
        $booking = $this->awaitingBooking();
        $booking->markFeePaid();

        $this->actingAs($this->seller)->delete(route('product.destroy', $booking->product_id))
            ->assertSessionHas('error');

        $this->assertModelExists($booking->fresh());
        $this->assertSame(Booking::FEE_PAID, $booking->fresh()->fee_status);
    }

    public function test_seller_can_delete_product_after_payment_failed(): void
    {
        $booking = $this->awaitingBooking();
        $booking->failPayment('expire');
        $product = $booking->product;

        $this->actingAs($this->seller)->delete(route('product.destroy', $product->id))
            ->assertSessionHas('success');

        $this->assertModelMissing($product);
    }
}

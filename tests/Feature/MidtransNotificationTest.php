<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Tests\TestCase;

/**
 * Webhook notifikasi Midtrans: signature, idempotensi notifikasi ganda, dan setiap transaction_status.
 * Semua payload sintetis; server key hanya string palsu khusus test.
 */
class MidtransNotificationTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'test-server-key';
    private const ORDER_ID = 'TL-BKG-01JTESTORDER0000000000001';

    private User $seller;
    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();
        config(['midtrans.server_key' => self::KEY]);
        $this->seller = User::factory()->create(['role' => 'penjual', 'seller_status' => 'verified', 'nama_toko' => 'Lapak Nagoya']);
        $this->buyer = User::factory()->create(['role' => 'pembeli']);
    }

    private function awaitingBooking(): Booking
    {
        $product = Product::create(['user_id' => $this->seller->id, 'title' => 'Jaket Denim', 'price' => 50000, 'image_url' => 'products/x.jpg', 'status' => 'Booked']);

        return Booking::create([
            'product_id' => $product->id, 'user_id' => $this->buyer->id, 'qr_token' => 'TL-NOTIF001',
            'cod_location' => 'Nagoya Hill', 'cod_schedule' => now()->addDay(), 'status_cod' => Booking::AWAITING_PAYMENT,
            'booking_fee' => 2000, 'fee_status' => Booking::FEE_PENDING,
            'payment_order_id' => self::ORDER_ID, 'payment_status' => 'pending',
            'payment_qr_string' => 'qris-palsu', 'payment_expires_at' => now()->addMinutes(15),
        ]);
    }

    private function notify(string $status, array $overrides = [], ?string $key = self::KEY)
    {
        $payload = $overrides + [
            'order_id' => self::ORDER_ID, 'status_code' => $status === 'settlement' ? '200' : ($status === 'pending' ? '201' : '202'),
            'gross_amount' => '2000.00', 'transaction_status' => $status, 'payment_type' => 'qris',
        ];
        $payload['signature_key'] ??= hash('sha512', $payload['order_id'] . $payload['status_code'] . $payload['gross_amount'] . $key);

        return $this->postJson(route('payments.midtrans.notification'), $payload);
    }

    private function adminBookingFees(): int
    {
        $admin = User::factory()->create(['role' => 'admin']);

        return $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->viewData('stats')['bookingFees'];
    }

    public function test_valid_settlement_marks_fee_paid(): void
    {
        $booking = $this->awaitingBooking();

        $this->notify('settlement')->assertOk()->assertExactJson(['status' => 'paid']);

        $booking->refresh();
        $this->assertSame('Pending', $booking->status_cod);
        $this->assertSame(Booking::FEE_PAID, $booking->fee_status);
        $this->assertNotNull($booking->paid_at);
        $this->assertSame('Booked', $booking->product->status);
        $this->assertSame(2000, $this->adminBookingFees());
    }

    public function test_invalid_signature_is_rejected_without_changes(): void
    {
        $booking = $this->awaitingBooking();

        $this->notify('settlement', [], 'kunci-palsu-lain')->assertForbidden();
        $this->notify('settlement', ['signature_key' => str_repeat('0', 128)])->assertForbidden();
        $this->notify('settlement', ['signature_key' => ''])->assertForbidden();

        $this->assertSame(Booking::AWAITING_PAYMENT, $booking->fresh()->status_cod);
        $this->assertSame(Booking::FEE_PENDING, $booking->fresh()->fee_status);
    }

    public function test_webhook_rejects_everything_when_server_key_is_not_configured(): void
    {
        $booking = $this->awaitingBooking();
        config(['midtrans.server_key' => null]);

        $this->notify('settlement', [], '')->assertForbidden();
        $this->assertSame(Booking::AWAITING_PAYMENT, $booking->fresh()->status_cod);
    }

    public function test_duplicate_settlement_has_no_double_effect(): void
    {
        $booking = $this->awaitingBooking();

        $this->notify('settlement')->assertOk()->assertExactJson(['status' => 'paid']);
        $paidAt = $booking->fresh()->paid_at;
        $this->travel(3)->minutes();
        $this->notify('settlement')->assertOk()->assertExactJson(['status' => 'ignored']);
        $this->notify('settlement')->assertOk()->assertExactJson(['status' => 'ignored']);

        $booking->refresh();
        $this->assertSame(Booking::FEE_PAID, $booking->fee_status);
        $this->assertEquals($paidAt, $booking->paid_at);
        $this->assertSame(1, Booking::count());
        $this->assertSame(2000, $this->adminBookingFees());
    }

    public function test_expire_releases_product_and_repeated_expire_is_ignored(): void
    {
        $booking = $this->awaitingBooking();

        $this->notify('expire')->assertOk()->assertExactJson(['status' => 'released']);
        $this->notify('expire')->assertOk()->assertExactJson(['status' => 'ignored']);

        $booking->refresh();
        $this->assertSame('Cancelled', $booking->status_cod);
        $this->assertSame(Booking::CANCELLED_BY_SYSTEM, $booking->cancelled_by);
        $this->assertSame('Pembayaran QRIS kedaluwarsa', $booking->cancel_reason);
        $this->assertNull($booking->fee_status);
        $this->assertSame('Available', $booking->product->status);
        $this->assertSame(0, $this->adminBookingFees());
    }

    public function test_expire_after_settlement_does_not_undo_payment(): void
    {
        $booking = $this->awaitingBooking();

        $this->notify('settlement')->assertOk();
        $this->notify('expire')->assertOk()->assertExactJson(['status' => 'ignored']);

        $this->assertSame('Pending', $booking->fresh()->status_cod);
        $this->assertSame(Booking::FEE_PAID, $booking->fresh()->fee_status);
    }

    public function test_late_settlement_after_expire_is_platform_owned_once(): void
    {
        $booking = $this->awaitingBooking();

        $this->notify('expire')->assertOk();
        $this->notify('settlement')->assertOk()->assertExactJson(['status' => 'late_forfeited']);
        $this->notify('settlement')->assertOk()->assertExactJson(['status' => 'ignored']);

        $booking->refresh();
        $this->assertSame('Cancelled', $booking->status_cod);
        $this->assertSame(Booking::FEE_FORFEITED, $booking->fee_status);
        $this->assertSame(2000, $this->adminBookingFees());
    }

    public function test_cancel_and_deny_release_the_product(): void
    {
        foreach (['cancel' => 'Pembayaran QRIS dibatalkan', 'deny' => 'Pembayaran QRIS ditolak'] as $status => $note) {
            Booking::query()->delete();
            Product::query()->delete();
            $booking = $this->awaitingBooking();

            $this->notify($status)->assertOk()->assertExactJson(['status' => 'released']);

            $this->assertSame($note, $booking->fresh()->cancel_reason);
            $this->assertSame('Available', $booking->product->fresh()->status);
        }
    }

    public function test_pending_and_refund_change_nothing(): void
    {
        $booking = $this->awaitingBooking();

        $this->notify('pending')->assertOk()->assertExactJson(['status' => 'ignored']);
        $this->assertSame(Booking::AWAITING_PAYMENT, $booking->fresh()->status_cod);

        // Biaya booking tidak pernah dikembalikan: notifikasi refund tidak mengubah status fee
        $this->notify('settlement')->assertOk();
        $this->notify('refund')->assertOk()->assertExactJson(['status' => 'ignored']);
        $this->assertSame(Booking::FEE_PAID, $booking->fresh()->fee_status);
    }

    public function test_amount_mismatch_is_rejected(): void
    {
        $booking = $this->awaitingBooking();

        $this->notify('settlement', ['gross_amount' => '1.00'])->assertStatus(422);

        $this->assertSame(Booking::AWAITING_PAYMENT, $booking->fresh()->status_cod);
    }

    public function test_unknown_order_is_acknowledged_and_ignored(): void
    {
        $this->notify('settlement', ['order_id' => 'payment_notif_test_G123'])->assertOk()->assertExactJson(['status' => 'ignored']);
    }

    public function test_webhook_path_is_excluded_from_csrf(): void
    {
        // Middleware CSRF dilewati otomatis saat test; matikan pengecualian itu untuk menguji daftar except
        $csrf = new class ($this->app, $this->app['encrypter']) extends ValidateCsrfToken {
            protected function runningUnitTests()
            {
                return false;
            }
        };
        $next = fn () => response('ok');

        $webhook = Request::create('/payments/midtrans/notification', 'POST');
        $webhook->setLaravelSession($this->app['session']->driver());
        $this->assertSame('ok', $csrf->handle($webhook, $next)->getContent());

        $other = Request::create('/seller/verify-qr', 'POST');
        $other->setLaravelSession($this->app['session']->driver());
        $this->expectException(TokenMismatchException::class);
        $csrf->handle($other, $next);
    }
}

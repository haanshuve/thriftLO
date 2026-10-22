<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Order;
use App\Models\Product;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Permintaan ganda berurutan (klik dua kali, refresh, tab lama, scheduler + aksi manual) untuk
 * booking COD, pembayaran, escrow, konfirmasi terima, verifikasi QR, dan pembatalan booking.
 * Skenario "bersamaan" disimulasikan dengan dua instance model yang dimuat sebelum salah satunya bertindak.
 */
class DoubleRequestSafetyTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;
    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seller = User::factory()->create(['role' => 'penjual', 'seller_status' => 'verified', 'nama_toko' => 'Lapak Nagoya', 'lokasi_lapak' => 'Nagoya']);
        $this->buyer = User::factory()->create(['role' => 'pembeli']);
    }

    private function product(): Product
    {
        return Product::create([
            'user_id' => $this->seller->id, 'title' => 'Jaket Denim', 'price' => 150000, 'image_url' => 'products/x.jpg',
            'status' => 'Available', 'shipping_options' => [['courier' => 'JNE', 'cost' => 15000]],
        ]);
    }

    private function book(Product $product, string $key, ?User $as = null)
    {
        return $this->actingAs($as ?? $this->buyer)->post(route('product.book', $product->id), [
            'idempotency_key' => $key, 'lokasi_cod' => 'Nagoya Hill', 'waktu_cod' => now()->addDay()->toDateTimeString(), 'qris_paid' => 1,
        ]);
    }

    private function bookedProduct(): array
    {
        $product = $this->product();
        $this->book($product, $this->formKey());

        return [$product->fresh(), Booking::sole()];
    }

    private function shippedOrder(): Order
    {
        $product = $this->product();
        $this->actingAs($this->buyer)->post(route('order.store', $product), [
            'idempotency_key' => $this->formKey(), 'shipping_option' => 0, 'shipping_address' => 'Pekanbaru',
        ]);
        $order = Order::sole();
        $this->actingAs($this->seller)->patch(route('order.ship', $order));

        return $order->fresh();
    }

    private function buyerCancel(Booking $booking)
    {
        return $this->actingAs($this->buyer)->patch(route('booking.cancel', $booking), ['setuju_konsekuensi' => '1', 'alasan' => 'berubah_pikiran']);
    }

    private function sellerCancel(Booking $booking, string $action = 'tayangkan')
    {
        return $this->actingAs($this->seller)->patch(route('booking.sellerCancel', $booking), ['alasan' => 'barang_rusak', 'tindakan_produk' => $action]);
    }

    // --- 1. Booking COD -------------------------------------------------------------------------

    public function test_same_buyer_booking_twice_with_fresh_forms_gets_one_booking(): void
    {
        $product = $this->product();

        $this->book($product, $this->formKey())->assertSessionHas('success');
        $this->book($product, $this->formKey())->assertSessionHas('error');

        $this->assertSame(1, Booking::count());
        $this->assertSame(2000, (int) Booking::sum('booking_fee'));
        $this->assertSame('Booked', $product->fresh()->status);
    }

    public function test_two_buyers_racing_for_same_product_one_wins_other_gets_friendly_message(): void
    {
        $product = $this->product();
        $other = User::factory()->create(['role' => 'pembeli']);

        $this->book($product, $this->formKey())->assertSessionHas('success');
        $this->book($product, $this->formKey(), $other)
            ->assertSessionHas('error', fn ($msg) => str_contains($msg, 'di-booking orang lain'));

        $this->assertSame($this->buyer->id, Booking::sole()->user_id);
    }

    // --- 2. Pembayaran booking (QRIS simulasi) & langganan ----------------------------------------

    public function test_booking_payment_resubmitted_three_times_charges_once(): void
    {
        $product = $this->product();
        $key = $this->formKey();

        $this->book($product, $key)->assertSessionHas('success');
        $this->book($product, $key)->assertSessionHas('success', fn ($msg) => str_contains($msg, 'tidak ditagih lagi'));
        $this->book($product, $key)->assertRedirect(route('bookings.index'));

        $this->assertSame(1, Booking::where('fee_status', Booking::FEE_PAID)->count());
    }

    public function test_subscription_payment_double_click_creates_one_payment_and_one_period(): void
    {
        $key = $this->formKey();

        $this->actingAs($this->seller)->post(route('subscription.pay'), ['idempotency_key' => $key])->assertSessionHas('success');
        $expires = $this->seller->fresh()->subscription_expires_at;
        $this->actingAs($this->seller)->post(route('subscription.pay'), ['idempotency_key' => $key])->assertSessionHas('success');

        $this->assertSame(1, SubscriptionPayment::count());
        $this->assertEquals($expires, $this->seller->fresh()->subscription_expires_at);
    }

    // --- 3. Escrow ----------------------------------------------------------------------------------

    public function test_ship_clicked_twice_keeps_first_shipped_at(): void
    {
        $order = $this->shippedOrder();
        $order->forceFill(['shipped_at' => now()->subDays(2)])->save();
        $shippedAt = $order->fresh()->shipped_at;

        $this->actingAs($this->seller)->patch(route('order.ship', $order))->assertSessionHas('error');

        $this->assertEquals($shippedAt, $order->fresh()->shipped_at);
    }

    public function test_concurrent_ship_requests_mark_shipped_once(): void
    {
        $product = $this->product();
        $this->actingAs($this->buyer)->post(route('order.store', $product), [
            'idempotency_key' => $this->formKey(), 'shipping_option' => 0, 'shipping_address' => 'Pekanbaru',
        ]);

        $first = Order::sole();
        $second = Order::find($first->id); // dimuat saat masih Awaiting Shipment

        $this->assertTrue($first->markShipped());
        $this->assertFalse($second->markShipped());
        $this->assertSame('Sold Out', $product->fresh()->status);
    }

    public function test_scheduler_twice_and_manual_confirm_deduct_commission_once(): void
    {
        $order = $this->shippedOrder();
        $order->forceFill(['shipped_at' => now()->subDays(3)->subMinute()])->save();

        $this->artisan('thriftlo:release-escrow')->expectsOutputToContain('1 pesanan')->assertSuccessful();
        $this->artisan('thriftlo:release-escrow')->expectsOutputToContain('0 pesanan')->assertSuccessful();
        $this->actingAs($this->buyer)->patch(route('order.confirm', $order))->assertSessionHas('error');

        $order->refresh();
        $this->assertSame(Order::RELEASED_AUTOMATICALLY, $order->release_method);
        $this->assertSame(3000, $order->biaya_platform);
        $this->assertSame(3000, (int) Order::sum('biaya_platform'));
    }

    public function test_concurrent_auto_release_and_buyer_confirm_only_one_succeeds(): void
    {
        $order = $this->shippedOrder();

        // Dua proses memuat pesanan yang sama saat masih Shipped, lalu sama-sama mencoba mencairkan
        $schedulerCopy = Order::find($order->id);
        $buyerCopy = Order::find($order->id);

        $this->assertTrue($buyerCopy->release(Order::RELEASED_BY_BUYER));
        $this->assertFalse($schedulerCopy->release(Order::RELEASED_AUTOMATICALLY));

        $fresh = $order->fresh();
        $this->assertSame(Order::RELEASED_BY_BUYER, $fresh->release_method);
        $this->assertSame(3000, $fresh->biaya_platform);
    }

    public function test_escrow_scheduler_does_not_overlap(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($e) => str_contains($e->command ?? '', 'thriftlo:release-escrow'));

        $this->assertNotNull($event);
        $this->assertTrue($event->withoutOverlapping);
    }

    // --- 4. Konfirmasi terima, verifikasi QR, pembatalan ---------------------------------------------

    public function test_buyer_confirm_twice_releases_once(): void
    {
        $order = $this->shippedOrder();

        $this->actingAs($this->buyer)->patch(route('order.confirm', $order))->assertSessionHas('success');
        $releasedAt = $order->fresh()->released_at;
        $this->actingAs($this->buyer)->patch(route('order.confirm', $order))->assertSessionHas('error');

        $this->assertEquals($releasedAt, $order->fresh()->released_at);
        $this->assertSame(3000, (int) Order::sum('biaya_platform'));
    }

    public function test_qr_token_validated_twice_completes_once(): void
    {
        [$product, $booking] = $this->bookedProduct();

        $this->actingAs($this->seller)->post(route('booking.verify'), ['qr_code_token' => $booking->qr_token])->assertSessionHas('success');
        $this->actingAs($this->seller)->post(route('booking.verify'), ['qr_code_token' => strtolower($booking->qr_token)])
            ->assertSessionHas('error', fn ($msg) => str_contains($msg, 'sudah pernah diverifikasi'));

        $this->assertSame('Completed', $booking->fresh()->status_cod);
        $this->assertSame('Sold Out', $product->fresh()->status);
    }

    public function test_cancel_after_qr_verified_is_rejected(): void
    {
        [$product, $booking] = $this->bookedProduct();
        $this->actingAs($this->seller)->post(route('booking.verify'), ['qr_code_token' => $booking->qr_token]);

        $this->buyerCancel($booking)->assertSessionHas('error');
        $this->sellerCancel($booking)->assertSessionHas('error');

        $this->assertSame('Completed', $booking->fresh()->status_cod);
        $this->assertSame('Sold Out', $product->fresh()->status);
    }

    public function test_buyer_cancel_twice_cancels_once(): void
    {
        [$product, $booking] = $this->bookedProduct();

        $this->buyerCancel($booking)->assertSessionHas('success');
        $cancelledAt = $booking->fresh()->cancelled_at;
        $this->buyerCancel($booking)->assertSessionHas('error');

        $fresh = $booking->fresh();
        $this->assertEquals($cancelledAt, $fresh->cancelled_at);
        $this->assertSame(Booking::CANCELLED_BY_BUYER, $fresh->cancelled_by);
        $this->assertNull($fresh->active_product_id);
        $this->assertSame('Available', $product->fresh()->status);
    }

    public function test_seller_cancel_twice_does_not_relist_product_taken_down_first(): void
    {
        [$product, $booking] = $this->bookedProduct();

        $this->sellerCancel($booking, 'nonaktifkan')->assertSessionHas('success');
        $this->sellerCancel($booking, 'tayangkan')->assertSessionHas('error');

        $this->assertSame('Sold Out', $product->fresh()->status);
        $this->assertSame(Booking::CANCELLED_BY_SELLER, $booking->fresh()->cancelled_by);
    }

    public function test_concurrent_buyer_and_seller_cancel_only_first_wins(): void
    {
        [, $booking] = $this->bookedProduct();

        $buyerCopy = Booking::find($booking->id);
        $sellerCopy = Booking::find($booking->id);

        $this->assertTrue($buyerCopy->cancelByBuyer('berubah_pikiran'));
        $this->assertFalse($sellerCopy->cancelBySeller('barang_rusak', relistProduct: false));

        $fresh = $booking->fresh();
        $this->assertSame(Booking::CANCELLED_BY_BUYER, $fresh->cancelled_by);
        $this->assertSame('berubah_pikiran', $fresh->cancel_reason);
    }

    public function test_product_can_be_booked_again_after_cancellation(): void
    {
        [$product, $booking] = $this->bookedProduct();
        $this->buyerCancel($booking);

        $other = User::factory()->create(['role' => 'pembeli']);
        $this->book($product, $this->formKey(), $other)->assertSessionHas('success');

        $this->assertSame(1, Booking::whereNotNull('active_product_id')->count());
    }
}

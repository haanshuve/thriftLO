<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Product;
use App\Models\User;
use App\Payments\BookingFeeGateway;
use App\Payments\PaymentException;
use App\Payments\QrisCharge;
use App\Payments\SimulationGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Alur booking COD dengan PAYMENT_DRIVER=midtrans memakai gateway palsu (tidak ada panggilan ke
 * Midtrans), dan memastikan mode simulasi tetap berjalan seperti sebelumnya.
 */
class MidtransCheckoutTest extends TestCase
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
            'user_id' => $this->seller->id, 'title' => 'Jaket Denim', 'price' => 100000, 'image_url' => 'products/x.jpg', 'status' => 'Available',
        ]);
    }

    private function fakeGateway(bool $fails = false): object
    {
        $gateway = new class ($fails) implements BookingFeeGateway {
            public array $charged = [];

            public function __construct(private bool $fails)
            {
            }

            public function isSimulation(): bool
            {
                return false;
            }

            public function createQrisCharge(Booking $booking): QrisCharge
            {
                if ($this->fails) {
                    throw new PaymentException('gateway palsu menolak');
                }
                $this->charged[] = $booking->payment_order_id;

                return new QrisCharge('00020101021226QRIS-PALSU-' . $booking->payment_order_id, now()->addMinutes(15));
            }
        };
        $this->app->instance(BookingFeeGateway::class, $gateway);

        return $gateway;
    }

    private function book(Product $product, string $key, array $extra = [])
    {
        return $this->actingAs($this->buyer)->post(route('product.book', $product->id), $extra + [
            'idempotency_key' => $key, 'lokasi_cod' => 'Nagoya Hill', 'waktu_cod' => now()->addDay()->toDateTimeString(),
        ]);
    }

    public function test_midtrans_booking_waits_for_payment_and_shows_qris(): void
    {
        $gateway = $this->fakeGateway();
        $product = $this->product();

        $response = $this->book($product, (string) Str::uuid());

        $booking = Booking::sole();
        $response->assertRedirect(route('booking.pay', $booking));
        $this->assertSame(Booking::AWAITING_PAYMENT, $booking->status_cod);
        $this->assertSame(Booking::FEE_PENDING, $booking->fee_status);
        $this->assertNull($booking->paid_at);
        $this->assertStringStartsWith('TL-BKG-', $booking->payment_order_id);
        $this->assertSame([$booking->payment_order_id], $gateway->charged);
        $this->assertSame('Booked', $product->fresh()->status);

        $this->actingAs($this->buyer)->get(route('booking.pay', $booking))
            ->assertOk()->assertSee('Rp2.000')->assertSee('<svg', false)->assertDontSee($booking->qr_token);
        $this->actingAs($this->buyer)->getJson(route('booking.payStatus', $booking))->assertExactJson(['status' => 'awaiting']);
    }

    public function test_resubmitting_the_form_does_not_charge_twice(): void
    {
        $gateway = $this->fakeGateway();
        $product = $this->product();
        $key = (string) Str::uuid();

        $this->book($product, $key);
        $this->book($product, $key)->assertRedirect(route('booking.pay', Booking::sole()));

        $this->assertCount(1, $gateway->charged);
    }

    public function test_only_the_buyer_can_open_the_payment_page(): void
    {
        $this->fakeGateway();
        $this->book($this->product(), (string) Str::uuid());
        $booking = Booking::sole();

        $this->actingAs($this->seller)->get(route('booking.pay', $booking))->assertForbidden();
        $this->actingAs($this->seller)->getJson(route('booking.payStatus', $booking))->assertForbidden();
    }

    public function test_paid_booking_redirects_from_payment_page_to_tickets(): void
    {
        $this->fakeGateway();
        $this->book($this->product(), (string) Str::uuid());
        $booking = Booking::sole();
        $booking->markFeePaid();

        $this->actingAs($this->buyer)->getJson(route('booking.payStatus', $booking))->assertExactJson(['status' => 'paid']);
        $this->actingAs($this->buyer)->get(route('booking.pay', $booking))
            ->assertRedirect(route('bookings.index'))
            ->assertSessionHas('success', fn ($msg) => str_contains($msg, $booking->qr_token));
    }

    public function test_expired_qris_releases_product_when_payment_page_is_opened(): void
    {
        $this->fakeGateway();
        $product = $this->product();
        $this->book($product, (string) Str::uuid());
        $booking = Booking::sole();

        $this->travel(16)->minutes();

        $this->actingAs($this->buyer)->get(route('booking.pay', $booking))
            ->assertRedirect(route('product.show', $product->id))
            ->assertSessionHas('error', fn ($msg) => str_starts_with($msg, 'Pembayaran QRIS kedaluwarsa.'));
        $this->assertSame('Cancelled', $booking->fresh()->status_cod);
        $this->assertNull($booking->fresh()->fee_status);
        $this->assertSame('Available', $product->fresh()->status);
    }

    public function test_charge_failure_releases_product_without_fee(): void
    {
        $this->fakeGateway(fails: true);
        $product = $this->product();

        $this->book($product, (string) Str::uuid())
            ->assertRedirect(route('product.show', $product->id))
            ->assertSessionHas('error', 'Pembayaran QRIS sedang tidak bisa dibuat. Barangnya belum dikunci dan kamu tidak dikenai biaya. Coba lagi sebentar lagi, ya.');

        $booking = Booking::sole();
        $this->assertSame('Cancelled', $booking->status_cod);
        $this->assertSame('Tagihan QRIS gagal dibuat', $booking->cancel_reason);
        $this->assertNull($booking->fee_status);
        $this->assertSame('Available', $product->fresh()->status);
    }

    public function test_midtrans_modal_has_no_simulated_paid_button(): void
    {
        $this->fakeGateway();

        $this->actingAs($this->buyer)->get(route('product.show', $this->product()))
            ->assertOk()->assertDontSee('Saya sudah bayar')->assertDontSee('name="qris_paid"', false);
    }

    public function test_awaiting_booking_is_shown_with_pay_link_but_without_cod_token(): void
    {
        $this->fakeGateway();
        $this->book($this->product(), (string) Str::uuid());
        $booking = Booking::sole();

        $this->actingAs($this->buyer)->get(route('bookings.index'))
            ->assertOk()->assertSee('Menunggu pembayaran')->assertSee(route('booking.pay', $booking))
            ->assertDontSee($booking->qr_token)->assertDontSee('Tampilkan QR untuk COD');

        $this->actingAs($this->seller)->get(route('dashboard'))
            ->assertOk()->assertSee('Pembeli sedang membayar biaya booking lewat QRIS.')->assertDontSee('Batalkan (penjual)');

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.transactions'))->assertOk()->assertSee('Menunggu pembayaran');
    }

    public function test_simulation_driver_is_the_default_and_unchanged(): void
    {
        $this->assertSame('simulation', config('payment.driver'));
        $this->assertInstanceOf(SimulationGateway::class, app(BookingFeeGateway::class));

        $product = $this->product();
        $this->actingAs($this->buyer)->get(route('product.show', $product))->assertOk()->assertSee('Saya sudah bayar');

        $this->book($product, (string) Str::uuid(), ['qris_paid' => 1])->assertRedirect(route('bookings.index'));

        $booking = Booking::sole();
        $this->assertSame('Pending', $booking->status_cod);
        $this->assertSame(Booking::FEE_PAID, $booking->fee_status);
        $this->assertNotNull($booking->paid_at);
        $this->assertNull($booking->payment_order_id);
    }
}

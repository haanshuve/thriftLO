<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Aturan biaya booking COD: biaya booking selalu menjadi milik platform dan tidak pernah
 * dikembalikan, baik pembeli maupun penjual yang membatalkan.
 */
class BookingFeeRuleTest extends TestCase
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

    private function booking(string $title = 'Jaket Denim', string $token = 'TL-AAAA1111'): Booking
    {
        $product = Product::create(['user_id' => $this->seller->id, 'title' => $title, 'price' => 50000, 'image_url' => 'products/x.jpg', 'status' => 'Booked']);

        return Booking::create([
            'product_id' => $product->id, 'user_id' => $this->buyer->id, 'qr_token' => $token,
            'cod_location' => 'Nagoya Hill', 'cod_schedule' => now()->addDay(), 'status_cod' => 'Pending',
            'booking_fee' => 2000, 'fee_status' => Booking::FEE_PAID, 'paid_at' => now(),
        ]);
    }

    public function test_buyer_cancellation_makes_fee_platform_owned(): void
    {
        $booking = $this->booking();

        $this->actingAs($this->buyer)->patch(route('booking.cancel', $booking), ['setuju_konsekuensi' => '1', 'alasan' => 'berubah_pikiran'])
            ->assertSessionHas('success', 'Booking "Jaket Denim" sudah dibatalkan dan token QR-nya tidak berlaku lagi. Biaya booking Rp2.000 tidak dapat dikembalikan dan menjadi milik platform.');

        $booking->refresh();
        $this->assertSame(Booking::CANCELLED_BY_BUYER, $booking->cancelled_by);
        $this->assertSame(Booking::FEE_FORFEITED, $booking->fee_status);
        $this->assertSame('Available', $booking->product->status);
    }

    public function test_seller_cancellation_makes_fee_platform_owned_and_relists_product(): void
    {
        $booking = $this->booking();

        $this->actingAs($this->seller)->patch(route('booking.sellerCancel', $booking), ['alasan' => 'tidak_bisa_bertemu', 'tindakan_produk' => 'tayangkan'])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('success', 'Booking "Jaket Denim" dibatalkan. Biaya booking Rp2.000 yang dibayar pembeli tidak dapat dikembalikan dan menjadi milik platform. Barang tayang lagi di katalog.');

        $booking->refresh();
        $this->assertSame(Booking::CANCELLED_BY_SELLER, $booking->cancelled_by);
        $this->assertSame(Booking::FEE_FORFEITED, $booking->fee_status);
        $this->assertSame('Milik platform', $booking->feeStatusLabel());
        $this->assertSame('Available', $booking->product->status);
    }

    public function test_seller_cancellation_with_takedown_also_keeps_fee(): void
    {
        $booking = $this->booking();

        $this->actingAs($this->seller)->patch(route('booking.sellerCancel', $booking), ['alasan' => 'sudah_terjual', 'tindakan_produk' => 'nonaktifkan'])
            ->assertSessionHas('success');

        $booking->refresh();
        $this->assertSame(Booking::FEE_FORFEITED, $booking->fee_status);
        $this->assertSame('Sold Out', $booking->product->status);
    }

    public function test_no_cancellation_path_creates_refunded_status(): void
    {
        $this->assertTrue($this->booking('Jaket Denim', 'TL-AAAA1111')->cancelByBuyer(null));
        $this->assertTrue($this->booking('Sepatu Lari', 'TL-BBBB2222')->cancelBySeller('barang_rusak', relistProduct: true));
        $this->assertTrue($this->booking('Kamera Analog', 'TL-CCCC3333')->cancelBySeller('lainnya', relistProduct: false));

        $this->assertSame(0, Booking::where('fee_status', Booking::FEE_REFUNDED)->count());
        $this->assertSame(3, Booking::where('fee_status', Booking::FEE_FORFEITED)->count());
    }

    public function test_seller_cancel_modal_and_buyer_history_say_fee_is_not_refunded(): void
    {
        $booking = $this->booking();

        $this->actingAs($this->seller)->get(route('dashboard'))->assertOk()
            ->assertSee('yang dibayar pembeli tidak dapat dikembalikan dan menjadi milik platform')
            ->assertDontSee('akan dikembalikan ke pembeli');

        $booking->cancelBySeller('barang_rusak', relistProduct: true);

        $this->actingAs($this->buyer)->get(route('bookings.index', ['tab' => 'riwayat']))->assertOk()
            ->assertSee('Penjual membatalkan booking ini')
            ->assertSee('Biaya booking Rp2.000 tidak dapat dikembalikan dan menjadi milik platform.')
            ->assertDontSee('dikembalikan ke kamu');
    }

    public function test_admin_stats_and_reports_count_fees_from_buyer_and_seller_cancellations(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->booking('Jaket Denim', 'TL-AAAA1111');                                    // masih aktif, dibayar
        $this->booking('Sepatu Lari', 'TL-BBBB2222')->cancelByBuyer(null);               // batal oleh pembeli
        $this->booking('Kamera Analog', 'TL-CCCC3333')->cancelBySeller('lainnya', true); // batal oleh penjual
        // Data lama berstatus refunded tidak dihitung
        $this->booking('Tas Kulit', 'TL-DDDD4444')->forceFill([
            'status_cod' => 'Cancelled', 'cancelled_by' => Booking::CANCELLED_BY_SELLER, 'fee_status' => Booking::FEE_REFUNDED,
        ])->save();

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()
            ->assertSee('Rp6.000')
            ->assertDontSee('Rp8.000');

        $this->actingAs($admin)->get(route('admin.reports'))->assertOk()
            ->assertSee('Rp6.000')
            ->assertDontSee('Rp8.000')
            ->assertSee('termasuk booking yang dibatalkan')
            ->assertSee('Refund pada data lama tidak dihitung.');
    }

    public function test_legacy_refunded_rows_still_render_without_error(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $booking = $this->booking();
        $booking->forceFill(['status_cod' => 'Cancelled', 'cancelled_by' => Booking::CANCELLED_BY_SELLER, 'fee_status' => Booking::FEE_REFUNDED])->save();

        $this->assertSame('Dikembalikan ke pembeli', $booking->fresh()->feeStatusLabel());
        $this->actingAs($admin)->get(route('admin.transactions'))->assertOk()->assertSee('Dikembalikan ke pembeli');
        $this->actingAs($this->buyer)->get(route('bookings.index', ['tab' => 'riwayat']))->assertOk();
    }
}

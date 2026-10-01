<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MyBookingsTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;
    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buyer = User::factory()->create(['role' => 'pembeli']);
        $this->seller = User::factory()->create(['role' => 'penjual', 'seller_status' => 'verified', 'nama_toko' => 'Nagoya Thrift']);
    }

    private function booking(string $status = 'Pending', string $token = 'TL-ABCD1234', ?User $buyer = null): Booking
    {
        $product = Product::create([
            'user_id' => $this->seller->id,
            'title' => 'Kamera Analog ' . $token,
            'price' => 1250000,
            'status' => $status === 'Completed' ? 'Sold Out' : 'Booked',
        ]);

        return Booking::create([
            'product_id' => $product->id,
            'user_id' => ($buyer ?? $this->buyer)->id,
            'qr_token' => $token,
            'cod_location' => 'Mega Mall',
            'cod_schedule' => '2026-10-05 14:00:00',
            'status_cod' => $status,
        ]);
    }

    public function test_active_ticket_shows_locally_generated_qr_and_chat_link(): void
    {
        $booking = $this->booking();

        $this->actingAs($this->buyer)->get('/my-bookings')->assertOk()
            ->assertSee('Nagoya Thrift')
            ->assertSee('Menunggu COD')
            ->assertSee('TL-ABCD1234')
            ->assertSee('05 Oct 2026, 14:00')
            ->assertSee('<svg', false)
            ->assertDontSee('api.qrserver.com')       // token tidak dikirim ke layanan luar
            ->assertSee(route('chat.index', ['user_id' => $this->seller->id, 'product_id' => $booking->product_id]));
    }

    public function test_qr_svg_is_inline_ready(): void
    {
        $svg = $this->booking()->qrCodeSvg();

        $this->assertStringStartsWith('<svg', $svg);
        $this->assertStringNotContainsString('<?xml', $svg);
    }

    public function test_tickets_are_split_into_active_and_history_tabs(): void
    {
        $this->booking('Pending', 'TL-AKTIF001');
        $this->booking('Completed', 'TL-SELESAI1');

        $this->actingAs($this->buyer)->get('/my-bookings')->assertOk()
            ->assertSee('Kamera Analog TL-AKTIF001')
            ->assertDontSee('Kamera Analog TL-SELESAI1');

        $this->actingAs($this->buyer)->get('/my-bookings?tab=riwayat')->assertOk()
            ->assertSee('Kamera Analog TL-SELESAI1')
            ->assertDontSee('Kamera Analog TL-AKTIF001')
            ->assertSee('Beri ulasan untuk penjual')
            ->assertDontSee('TL-SELESAI1</p>', false);  // QR/token tidak ditampilkan lagi setelah selesai
    }

    public function test_history_is_default_tab_when_there_are_no_active_tickets(): void
    {
        $this->booking('Completed', 'TL-SELESAI1');

        $this->actingAs($this->buyer)->get('/my-bookings')->assertOk()
            ->assertSee('Kamera Analog TL-SELESAI1');
    }

    public function test_buyer_only_sees_own_tickets(): void
    {
        $other = User::factory()->create(['role' => 'pembeli']);
        $this->booking('Pending', 'TL-ORGLAIN1', $other);

        $this->actingAs($this->buyer)->get('/my-bookings')->assertOk()
            ->assertDontSee('TL-ORGLAIN1')
            ->assertSee('Belum ada tiket aktif');
    }

    public function test_buyer_can_review_completed_ticket(): void
    {
        $booking = $this->booking('Completed');

        $this->actingAs($this->buyer)->from('/my-bookings?tab=riwayat')
            ->post(route('review.store', $booking->id), ['booking_id' => $booking->id, 'rating' => 4, 'comment' => 'Barang sesuai foto'])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('reviews', ['booking_id' => $booking->id, 'rating' => 4, 'seller_id' => $this->seller->id]);

        $this->actingAs($this->buyer)->get('/my-bookings?tab=riwayat')->assertOk()
            ->assertSee('4 dari 5 bintang')
            ->assertSee('Barang sesuai foto')
            ->assertDontSee('Beri ulasan untuk penjual');
    }

    public function test_review_without_rating_shows_error_on_the_right_ticket(): void
    {
        $booking = $this->booking('Completed');

        $this->actingAs($this->buyer)->from('/my-bookings?tab=riwayat')
            ->post(route('review.store', $booking->id), ['booking_id' => $booking->id, 'comment' => 'Mantap'])
            ->assertSessionHasErrorsIn('review', ['rating']);

        $this->assertSame(0, Review::count());

        $this->actingAs($this->buyer)->get('/my-bookings?tab=riwayat')->assertOk()
            ->assertSee('Pilih jumlah bintang terlebih dahulu.')
            ->assertSee('>Mantap</textarea>', false);
    }

    public function test_reviews_are_eager_loaded_instead_of_queried_per_ticket(): void
    {
        foreach (range(1, 5) as $i) {
            $this->booking('Completed', 'TL-RIWAYAT' . $i);
        }

        DB::enableQueryLog();
        $this->actingAs($this->buyer)->get('/my-bookings?tab=riwayat')->assertOk();
        $reviewQueries = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], '"reviews"'));

        $this->assertCount(1, $reviewQueries);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;
    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buyer = User::factory()->create(['role' => 'pembeli', 'name' => 'Raihan Hanafi']);
        $this->seller = User::factory()->create(['role' => 'penjual', 'seller_status' => 'verified', 'nama_toko' => 'Lapak Jodoh', 'lokasi_lapak' => 'Jodoh']);
    }

    private function product(string $title, string $status = 'Sold Out'): Product
    {
        return Product::create([
            'user_id' => $this->seller->id, 'title' => $title, 'price' => 100000,
            'image_url' => 'products/x.jpg', 'kategori' => 'Fashion', 'status' => $status,
        ]);
    }

    private function order(string $status = Order::COMPLETED, ?User $buyer = null): Order
    {
        $order = Order::create([
            'product_id' => $this->product('Jaket Kirim')->id, 'user_id' => ($buyer ?? $this->buyer)->id,
            'courier' => 'JNE', 'shipping_cost' => 15000, 'item_price' => 100000, 'total_price' => 115000,
            'shipping_address' => 'Pekanbaru', 'status' => $status,
        ]);
        if ($status !== Order::AWAITING_SHIPMENT) {
            $order->forceFill(['shipped_at' => now()->subDay()])->save();
        }
        if ($status === Order::COMPLETED) {
            $order->forceFill(['released_at' => now()])->save();
        }

        return $order;
    }

    private function completedBooking(string $title = 'Tas COD'): Booking
    {
        return Booking::create([
            'product_id' => $this->product($title)->id, 'user_id' => $this->buyer->id, 'qr_token' => 'TL-' . strtoupper(substr(md5($title), 0, 8)),
            'cod_location' => 'Nagoya', 'cod_schedule' => now(), 'status_cod' => 'Completed',
        ]);
    }

    private function review(int $rating, ?string $comment, ?Booking $booking = null): Review
    {
        $booking ??= $this->completedBooking('Barang ' . uniqid());

        return Review::create([
            'booking_id' => $booking->id, 'product_id' => $booking->product_id, 'seller_id' => $this->seller->id,
            'buyer_id' => $this->buyer->id, 'rating' => $rating, 'comment' => $comment,
        ]);
    }

    // ---------------------------------------------------------------- Ulasan pesanan kirim

    public function test_buyer_can_review_completed_shipping_order(): void
    {
        $order = $this->order();

        $this->actingAs($this->buyer)->get('/my-bookings')->assertOk()
            ->assertSee('Beri ulasan untuk penjual')
            ->assertSee(route('review.storeOrder', $order), false);

        $this->actingAs($this->buyer)->from('/my-bookings')
            ->post(route('review.storeOrder', $order), ['order_id' => $order->id, 'rating' => 5, 'comment' => '  Paket rapi, cepat sampai  '])
            ->assertRedirect('/my-bookings')
            ->assertSessionHas('success');

        $this->assertDatabaseHas('reviews', [
            'order_id' => $order->id, 'booking_id' => null, 'product_id' => $order->product_id,
            'seller_id' => $this->seller->id, 'buyer_id' => $this->buyer->id, 'rating' => 5, 'comment' => 'Paket rapi, cepat sampai',
        ]);

        $this->actingAs($this->buyer)->get('/my-bookings')->assertOk()
            ->assertSee('5 dari 5 bintang')
            ->assertSee('Paket rapi, cepat sampai')
            ->assertDontSee('Beri ulasan untuk penjual');
    }

    public function test_order_that_is_not_finished_cannot_be_reviewed(): void
    {
        $order = $this->order(Order::SHIPPED);

        $this->actingAs($this->buyer)->get('/my-bookings')->assertOk()->assertDontSee('Beri ulasan untuk penjual');

        $this->actingAs($this->buyer)->post(route('review.storeOrder', $order), ['order_id' => $order->id, 'rating' => 5])
            ->assertSessionHas('error');
        $this->assertSame(0, Review::count());
    }

    public function test_buyer_cannot_review_someone_elses_order(): void
    {
        $order = $this->order(buyer: User::factory()->create(['role' => 'pembeli']));

        $this->actingAs($this->buyer)->post(route('review.storeOrder', $order), ['order_id' => $order->id, 'rating' => 1])
            ->assertSessionHas('error');
        $this->assertSame(0, Review::count());
    }

    public function test_second_review_for_the_same_transaction_is_rejected(): void
    {
        $order = $this->order();
        $booking = $this->completedBooking();

        $this->actingAs($this->buyer)->post(route('review.storeOrder', $order), ['order_id' => $order->id, 'rating' => 5]);
        $this->actingAs($this->buyer)->post(route('review.storeOrder', $order), ['order_id' => $order->id, 'rating' => 1])
            ->assertSessionHas('error', 'Kamu sudah memberikan ulasan untuk transaksi ini.');

        $this->actingAs($this->buyer)->post(route('review.store', $booking->id), ['booking_id' => $booking->id, 'rating' => 4]);
        $this->actingAs($this->buyer)->post(route('review.store', $booking->id), ['booking_id' => $booking->id, 'rating' => 2])
            ->assertSessionHas('error');

        $this->assertSame([5, 4], Review::orderBy('id')->pluck('rating')->all());
    }

    public function test_rating_must_be_between_one_and_five(): void
    {
        $order = $this->order();

        foreach ([0, 6, 'abc'] as $bad) {
            $this->actingAs($this->buyer)->post(route('review.storeOrder', $order), ['order_id' => $order->id, 'rating' => $bad])
                ->assertSessionHasErrorsIn('review', ['rating']);
        }
        $this->actingAs($this->buyer)->post(route('review.storeOrder', $order), ['order_id' => $order->id, 'rating' => 3, 'comment' => str_repeat('a', 501)])
            ->assertSessionHasErrorsIn('review', ['comment']);

        $this->assertSame(0, Review::count());
    }

    // ---------------------------------------------------------------- Tampil di halaman produk

    public function test_product_page_shows_seller_rating_and_latest_comments(): void
    {
        $this->review(5, 'Barang sesuai video, penjual ramah');
        $this->review(4, null);
        $this->review(4, '<script>alert(1)</script>');

        $product = $this->product('Kemeja Flanel', 'Available');

        $this->get(route('product.show', $product))->assertOk()
            ->assertSee('id="ulasan"', false)
            ->assertSee('Ulasan untuk Lapak Jodoh')
            ->assertSee('4,3')                         // (5 + 4 + 4) / 3
            ->assertSee('3 ulasan')
            ->assertSee('Barang sesuai video, penjual ramah')
            ->assertSee('R****n')                      // nama pengulas disamarkan
            ->assertDontSee('Raihan Hanafi')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_product_page_shows_only_five_latest_reviews(): void
    {
        foreach (range(1, 7) as $n) {
            $this->review(5, "Komentar ke-{$n}");
        }

        $html = $this->get(route('product.show', $this->product('Topi', 'Available')))->assertOk()
            ->assertSee('7 ulasan')
            ->assertSee('Komentar ke-7')
            ->assertDontSee('Komentar ke-2')
            ->getContent();

        $this->assertSame(5, substr_count($html, 'data-review="'));
    }

    public function test_product_page_without_reviews_says_so(): void
    {
        $this->get(route('product.show', $this->product('Topi', 'Available')))->assertOk()
            ->assertSee('Belum ada ulasan')
            ->assertDontSee('data-review="', false);
    }

    public function test_reviews_of_other_sellers_are_not_counted(): void
    {
        $this->review(1, 'Ulasan penjual lain');
        $other = User::factory()->create(['role' => 'penjual', 'seller_status' => 'verified', 'nama_toko' => 'Toko Lain']);
        $product = Product::create(['user_id' => $other->id, 'title' => 'Sepatu', 'price' => 1, 'image_url' => 'p.jpg', 'kategori' => 'Sepatu', 'status' => 'Available']);

        $this->get(route('product.show', $product))->assertOk()
            ->assertSee('Belum ada ulasan')
            ->assertDontSee('Ulasan penjual lain');
    }

    public function test_seller_cannot_erase_reviews_by_deleting_the_sold_product(): void
    {
        $booking = $this->completedBooking('Jam Tangan Lama');
        $review = $this->review(1, 'Barang tidak sesuai', $booking);

        $product = $booking->product;

        $this->actingAs($this->seller)->delete(route('product.destroy', $product->id))->assertSessionHas('success');
        $this->assertModelMissing($product);

        $review->refresh();
        $this->assertNull($review->product_id);
        $this->assertNull($review->booking_id);

        $this->get(route('product.show', $this->product('Barang Lain', 'Available')))->assertOk()
            ->assertSee('1 ulasan')
            ->assertSee('Barang tidak sesuai')
            ->assertSee('Barang sudah dihapus');
    }
}

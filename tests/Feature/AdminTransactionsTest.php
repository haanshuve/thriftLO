<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTransactionsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $seller;
    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->seller = User::factory()->create(['role' => 'penjual', 'seller_status' => 'verified', 'nama_toko' => 'Lapak Nagoya']);
        $this->buyer = User::factory()->create(['role' => 'pembeli', 'name' => 'Sari Pembeli']);
    }

    private function product(string $title): Product
    {
        return Product::create(['user_id' => $this->seller->id, 'title' => $title, 'price' => 50000, 'image_url' => 'products/x.jpg', 'status' => 'Booked']);
    }

    private function booking(string $title, string $status, ?string $feeStatus = Booking::FEE_PAID, string $token = 'TL-AAAA1111'): Booking
    {
        $booking = Booking::create([
            'product_id' => $this->product($title)->id, 'user_id' => $this->buyer->id, 'qr_token' => $token,
            'cod_location' => 'Nagoya Hill', 'cod_schedule' => now()->addDay(), 'status_cod' => $status,
            'booking_fee' => 2000, 'fee_status' => $feeStatus,
        ]);

        return $booking;
    }

    private function order(string $title, string $status): Order
    {
        return Order::create([
            'product_id' => $this->product($title)->id, 'user_id' => $this->buyer->id, 'courier' => 'JNE',
            'shipping_cost' => 10000, 'item_price' => 50000, 'total_price' => 60000,
            'shipping_address' => 'Pekanbaru', 'status' => $status,
        ]);
    }

    public function test_lists_cod_bookings_and_kirim_orders_with_fee_status(): void
    {
        $this->booking('Jaket Denim', 'Pending');
        $this->booking('Sepatu Lari', 'Cancelled', Booking::FEE_FORFEITED, 'TL-BBBB2222')
            ->forceFill(['cancelled_by' => Booking::CANCELLED_BY_BUYER])->save();
        $this->order('Kamera Analog', Order::AWAITING_SHIPMENT);

        $this->actingAs($this->admin)->get(route('admin.transactions'))->assertOk()
            ->assertSee('Booking COD')
            ->assertSee('Pesanan Kirim')
            ->assertSee('Jaket Denim')
            ->assertSee('Menunggu COD')
            ->assertSee('Dibayar')
            ->assertSee('Sepatu Lari')
            ->assertSee('Dibatalkan oleh pembeli')
            ->assertSee('Milik platform')
            ->assertSee('Kamera Analog')
            ->assertSee('Menunggu Dikirim')
            ->assertSee('Lapak Nagoya')
            ->assertSee('Sari Pembeli');
    }

    public function test_filters_by_type_and_status(): void
    {
        $this->booking('Jaket Denim', 'Pending');
        $this->booking('Sepatu Lari', 'Cancelled', Booking::FEE_FORFEITED, 'TL-BBBB2222')
            ->forceFill(['cancelled_by' => Booking::CANCELLED_BY_SELLER])->save();
        $this->order('Kamera Analog', Order::AWAITING_SHIPMENT);

        $this->actingAs($this->admin)->get(route('admin.transactions', ['jenis' => 'kirim']))->assertOk()
            ->assertSee('Kamera Analog')
            ->assertDontSee('Jaket Denim')
            ->assertDontSee('id="judulCod"', false);

        $this->actingAs($this->admin)->get(route('admin.transactions', ['jenis' => 'cod', 'status' => 'dibatalkan']))->assertOk()
            ->assertSee('Sepatu Lari')
            ->assertSee('Dibatalkan oleh penjual')
            ->assertSee('Milik platform')
            ->assertDontSee('Dikembalikan ke pembeli')
            ->assertDontSee('Jaket Denim')
            ->assertDontSee('Kamera Analog');

        $this->actingAs($this->admin)->get(route('admin.transactions', ['status' => 'berjalan']))->assertOk()
            ->assertSee('Jaket Denim')
            ->assertSee('Kamera Analog')
            ->assertDontSee('Sepatu Lari');
    }

    public function test_search_matches_product_and_qr_token(): void
    {
        $this->booking('Jaket Denim', 'Pending', Booking::FEE_PAID, 'TL-CARI1234');
        $this->booking('Sepatu Lari', 'Pending', Booking::FEE_PAID, 'TL-LAIN0000');
        $this->order('Kamera Analog', Order::SHIPPED);

        $this->actingAs($this->admin)->get(route('admin.transactions', ['q' => 'TL-CARI']))->assertOk()
            ->assertSee('Jaket Denim')
            ->assertDontSee('Sepatu Lari')
            ->assertDontSee('Kamera Analog');

        $this->actingAs($this->admin)->get(route('admin.transactions', ['q' => 'kamera']))->assertOk()
            ->assertSee('Kamera Analog')
            ->assertDontSee('Jaket Denim');
    }

    public function test_unknown_filters_fall_back_to_all(): void
    {
        $this->booking('Jaket Denim', 'Pending');

        $this->actingAs($this->admin)->get(route('admin.transactions', ['jenis' => 'ngawur', 'status' => 'x']))->assertOk()
            ->assertSee('Jaket Denim');
    }
}

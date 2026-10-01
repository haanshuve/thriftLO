<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ShippingTest extends TestCase
{
    use RefreshDatabase;

    private User $batamSeller;
    private User $jakartaSeller;
    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->batamSeller = $this->seller('Batam Vintage Hub', 'Batam Center');
        $this->jakartaSeller = $this->seller('Jakarta Retro', 'Luar Batam');
        $this->buyer = User::factory()->create(['name' => 'Budi', 'role' => 'pembeli']);
    }

    private function seller(string $shop, ?string $location): User
    {
        return User::factory()->create(['role' => 'penjual', 'seller_status' => 'verified', 'nama_toko' => $shop, 'lokasi_lapak' => $location]);
    }

    private function product(User $owner, string $title, ?array $shipping = null, string $status = 'Available'): Product
    {
        return Product::create([
            'user_id' => $owner->id,
            'title' => $title,
            'price' => 150000,
            'image_url' => 'products/barang.jpg',
            'status' => $status,
            'shipping_options' => $shipping,
        ]);
    }

    private function jneAndJnt(): array
    {
        return [['courier' => 'JNE Reguler', 'cost' => 18000], ['courier' => 'J&T Ekonomi', 'cost' => 12000]];
    }

    private function uploadAs(User $seller, array $shipping)
    {
        Storage::fake('public');

        return $this->actingAs($seller)->post(route('product.store'), [
            'nama_barang' => 'Kemeja Flanel',
            'mode_jual' => 'ecer',
            'kategori' => 'Fashion',
            'harga' => 90000,
            'grade' => config('thriftlo.grades')[0],
            'image' => UploadedFile::fake()->image('kemeja.jpg'),
            'shipping_options' => $shipping,
        ]);
    }

    // --- Deteksi Batam ---

    public function test_every_batam_area_option_allows_cod_except_luar_batam(): void
    {
        $this->assertTrue($this->seller('A', 'Batam Center')->isInBatam());
        $this->assertTrue($this->seller('B', 'Botania')->isInBatam());
        $this->assertTrue($this->seller('C', 'Batam lainnya')->isInBatam());
        $this->assertFalse($this->seller('D', 'Luar Batam')->isInBatam());
        $this->assertFalse($this->seller('E', null)->isInBatam());
    }

    public function test_catalog_card_shows_cod_for_batam_and_shipping_for_other_cities(): void
    {
        $this->product($this->batamSeller, 'Jaket Batam');
        $this->product($this->jakartaSeller, 'Sepatu Jakarta', $this->jneAndJnt());

        $html = $this->actingAs($this->buyer)->get('/')->assertOk()->getContent();

        $batamCard = $this->cardHtml($html, 'Jaket Batam');
        $this->assertStringContainsString('Booking COD', $batamCard);
        $this->assertStringNotContainsString('Pilih Pengiriman', $batamCard);

        $jakartaCard = $this->cardHtml($html, 'Sepatu Jakarta');
        $this->assertStringContainsString('Pilih Pengiriman', $jakartaCard);
        $this->assertStringNotContainsString('Booking COD', $jakartaCard);
    }

    public function test_detail_page_shows_cod_only_for_batam_seller(): void
    {
        $batam = $this->product($this->batamSeller, 'Jaket Batam');
        $jakarta = $this->product($this->jakartaSeller, 'Sepatu Jakarta', $this->jneAndJnt());

        $this->actingAs($this->buyer)->get(route('product.show', $batam))->assertOk()
            ->assertSee('Booking COD')
            ->assertDontSee('Pilih Pengiriman');

        $this->actingAs($this->buyer)->get(route('product.show', $jakarta))->assertOk()
            ->assertDontSee('Booking COD')
            ->assertSee('Pilih Pengiriman')
            ->assertSee('JNE Reguler')
            ->assertSee('Rp18.000')
            ->assertSee('J&amp;T Ekonomi', false);
    }

    public function test_batam_seller_with_shipping_options_offers_both(): void
    {
        $product = $this->product($this->batamSeller, 'Jaket Batam', $this->jneAndJnt());

        $this->actingAs($this->buyer)->get(route('product.show', $product))->assertOk()
            ->assertSee('Booking COD')
            ->assertSee('Pilih Pengiriman');
    }

    public function test_cod_booking_is_refused_for_seller_outside_batam(): void
    {
        $product = $this->product($this->jakartaSeller, 'Sepatu Jakarta', $this->jneAndJnt());

        $this->actingAs($this->buyer)->post(route('product.book', $product->id), [
            'lokasi_cod' => 'Mega Mall', 'waktu_cod' => now()->addDay()->format('Y-m-d\TH:i'),
        ])->assertRedirect(route('product.show', $product))->assertSessionHas('error');

        $this->assertSame(0, Booking::count());
        $this->assertSame('Available', $product->fresh()->status);
    }

    // --- Upload & edit opsi pengiriman ---

    public function test_seller_outside_batam_must_add_at_least_one_shipping_option(): void
    {
        $this->uploadAs($this->jakartaSeller, [['courier' => '', 'cost' => '']])
            ->assertSessionHasErrorsIn('product', 'shipping_options');
        $this->assertSame(0, Product::count());

        $this->uploadAs($this->jakartaSeller, $this->jneAndJnt())->assertSessionHasNoErrors();
        $this->assertSame($this->jneAndJnt(), Product::first()->shipping_options);
    }

    public function test_batam_seller_can_upload_without_shipping_options(): void
    {
        $this->uploadAs($this->batamSeller, [['courier' => '', 'cost' => '']])->assertSessionHasNoErrors();

        $this->assertNull(Product::first()->shipping_options);
    }

    public function test_incomplete_shipping_row_is_rejected(): void
    {
        $this->uploadAs($this->jakartaSeller, [['courier' => 'JNE Reguler', 'cost' => ''], ['courier' => '', 'cost' => '9000']])
            ->assertSessionHasErrorsIn('product', ['shipping_options.0.cost', 'shipping_options.1.courier']);

        $this->assertSame(0, Product::count());
    }

    public function test_seller_can_edit_product_and_shipping_options(): void
    {
        $product = $this->product($this->jakartaSeller, 'Sepatu Jakarta', $this->jneAndJnt());

        $this->actingAs($this->jakartaSeller)->get(route('product.edit', $product->id))->assertOk()
            ->assertSee('value="JNE Reguler"', false);

        $this->actingAs($this->jakartaSeller)->put(route('product.update', $product->id), [
            'nama_barang' => 'Sepatu Jakarta Edisi Baru',
            'mode_jual' => 'ecer',
            'kategori' => 'Sepatu',
            'harga' => 175000,
            'grade' => config('thriftlo.grades')[1],
            'shipping_options' => [['courier' => 'SiCepat REG', 'cost' => 15000]],
        ])->assertRedirect(route('dashboard'))->assertSessionHasNoErrors();

        $product->refresh();
        $this->assertSame('Sepatu Jakarta Edisi Baru', $product->title);
        $this->assertSame(175000, (int) $product->price);
        $this->assertSame([['courier' => 'SiCepat REG', 'cost' => 15000]], $product->shipping_options);
        $this->assertSame('products/barang.jpg', $product->image_url);
    }

    public function test_other_users_cannot_edit_product(): void
    {
        $product = $this->product($this->jakartaSeller, 'Sepatu Jakarta', $this->jneAndJnt());

        $this->actingAs($this->batamSeller)->get(route('product.edit', $product->id))->assertNotFound();
        $this->actingAs($this->batamSeller)->put(route('product.update', $product->id), ['nama_barang' => 'Dibajak'])->assertNotFound();
        $this->assertSame('Sepatu Jakarta', $product->fresh()->title);
    }

    // --- Checkout ---

    public function test_buyer_checkout_creates_order_with_chosen_courier_and_total(): void
    {
        $product = $this->product($this->jakartaSeller, 'Sepatu Jakarta', $this->jneAndJnt());

        $this->actingAs($this->buyer)->post(route('order.store', $product), [
            'shipping_option' => 1,
            'shipping_address' => 'Jl. Sudirman No. 1, Pekanbaru 28111',
        ])->assertRedirect(route('bookings.index'))->assertSessionHas('success');

        $order = Order::sole();
        $this->assertSame($this->buyer->id, $order->user_id);
        $this->assertSame('J&T Ekonomi', $order->courier);
        $this->assertSame(12000, (int) $order->shipping_cost);
        $this->assertSame(162000, (int) $order->total_price);
        $this->assertSame(Order::AWAITING_SHIPMENT, $order->status);
        $this->assertSame('Booked', $product->fresh()->status);
    }

    public function test_shipping_cost_comes_from_product_not_from_request(): void
    {
        $product = $this->product($this->jakartaSeller, 'Sepatu Jakarta', $this->jneAndJnt());

        $this->actingAs($this->buyer)->post(route('order.store', $product), [
            'shipping_option' => 0, 'shipping_address' => 'Pekanbaru', 'shipping_cost' => 1, 'total_price' => 1,
        ]);

        $this->assertSame(168000, (int) Order::sole()->total_price);
    }

    public function test_checkout_requires_valid_option_and_address(): void
    {
        $product = $this->product($this->jakartaSeller, 'Sepatu Jakarta', $this->jneAndJnt());

        $this->actingAs($this->buyer)->from(route('product.show', $product))
            ->post(route('order.store', $product), ['shipping_option' => 5, 'shipping_address' => 'Pekanbaru'])
            ->assertSessionHasErrors('shipping_option');

        $this->actingAs($this->buyer)->post(route('order.store', $product), ['shipping_option' => 0])
            ->assertSessionHasErrors('shipping_address');

        $this->assertSame(0, Order::count());
        $this->assertSame('Available', $product->fresh()->status);
    }

    public function test_cannot_checkout_own_or_unavailable_product(): void
    {
        $product = $this->product($this->jakartaSeller, 'Sepatu Jakarta', $this->jneAndJnt());
        $booked = $this->product($this->jakartaSeller, 'Tas Jakarta', $this->jneAndJnt(), 'Booked');

        $this->actingAs($this->jakartaSeller)->post(route('order.store', $product), ['shipping_option' => 0, 'shipping_address' => 'Jakarta'])
            ->assertSessionHas('error');
        $this->actingAs($this->buyer)->post(route('order.store', $booked), ['shipping_option' => 0, 'shipping_address' => 'Pekanbaru'])
            ->assertSessionHas('error');

        $this->assertSame(0, Order::count());
    }

    public function test_guest_is_sent_to_login_on_checkout(): void
    {
        $product = $this->product($this->jakartaSeller, 'Sepatu Jakarta', $this->jneAndJnt());

        $this->get(route('product.show', $product))->assertOk()->assertSee('Masuk untuk checkout');
        $this->post(route('order.store', $product), ['shipping_option' => 0, 'shipping_address' => 'Pekanbaru'])
            ->assertRedirect(route('login'));
    }

    // --- Status pesanan ---

    public function test_seller_sees_order_and_marks_it_shipped(): void
    {
        $product = $this->product($this->jakartaSeller, 'Sepatu Jakarta', $this->jneAndJnt());
        $this->actingAs($this->buyer)->post(route('order.store', $product), ['shipping_option' => 0, 'shipping_address' => 'Jl. Sudirman No. 1, Pekanbaru']);
        $order = Order::sole();

        $this->actingAs($this->jakartaSeller)->get('/dashboard')->assertOk()
            ->assertSee('Pesanan pengiriman')
            ->assertSee('Jl. Sudirman No. 1, Pekanbaru')
            ->assertSee('Menunggu Dikirim')
            ->assertSee('Tandai Sudah Dikirim');

        $this->actingAs($this->jakartaSeller)->patch(route('order.ship', $order))->assertSessionHas('success');

        $order->refresh();
        $this->assertSame(Order::SHIPPED, $order->status);
        $this->assertNotNull($order->shipped_at);
        $this->assertSame('Sold Out', $product->fresh()->status);
    }

    public function test_only_product_owner_can_mark_order_shipped(): void
    {
        $product = $this->product($this->jakartaSeller, 'Sepatu Jakarta', $this->jneAndJnt());
        $this->actingAs($this->buyer)->post(route('order.store', $product), ['shipping_option' => 0, 'shipping_address' => 'Pekanbaru']);
        $order = Order::sole();

        $this->actingAs($this->batamSeller)->patch(route('order.ship', $order))->assertForbidden();
        $this->actingAs($this->buyer)->patch(route('order.ship', $order))->assertForbidden();

        $this->assertSame(Order::AWAITING_SHIPMENT, $order->fresh()->status);
    }

    public function test_buyer_sees_order_status_in_tiket_saya(): void
    {
        $product = $this->product($this->jakartaSeller, 'Sepatu Jakarta', $this->jneAndJnt());
        $this->actingAs($this->buyer)->post(route('order.store', $product), ['shipping_option' => 0, 'shipping_address' => 'Pekanbaru']);

        $this->actingAs($this->buyer)->get(route('bookings.index'))->assertOk()
            ->assertSee('Pesanan pengiriman')
            ->assertSee('Sepatu Jakarta')
            ->assertSee('JNE Reguler')
            ->assertSee('Total Rp168.000')
            ->assertSee('Menunggu Dikirim');

        $this->actingAs($this->jakartaSeller)->patch(route('order.ship', Order::sole()));

        $this->actingAs($this->buyer)->get(route('bookings.index'))->assertOk()
            ->assertSee('Sudah Dikirim');
    }

    public function test_hidden_product_detail_is_not_found_for_others(): void
    {
        for ($i = 1; $i <= 6; $i++) {
            $this->product($this->jakartaSeller, "Barang {$i}", $this->jneAndJnt());
        }
        $hidden = Product::where('title', 'Barang 6')->first();

        $this->actingAs($this->buyer)->get(route('product.show', $hidden))->assertNotFound();
        $this->actingAs($this->jakartaSeller)->get(route('product.show', $hidden))->assertOk();
        $this->actingAs($this->buyer)->post(route('order.store', $hidden), ['shipping_option' => 0, 'shipping_address' => 'Pekanbaru'])
            ->assertSessionHas('error');
    }

    // Potongan HTML satu kartu produk di katalog, dari judul sampai kartu berikutnya
    private function cardHtml(string $html, string $title): string
    {
        $start = strrpos(substr($html, 0, strpos($html, $title)), '<article');
        $end = strpos($html, '</article>', $start);

        return substr($html, $start, $end - $start);
    }
}

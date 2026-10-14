<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminProductsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $seller;
    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->seller = User::factory()->create(['role' => 'penjual', 'seller_status' => 'verified', 'nama_toko' => 'Lapak Tiban', 'lokasi_lapak' => 'Tiban']);
        $this->buyer = User::factory()->create(['role' => 'pembeli']);
    }

    private function product(string $title = 'Jaket Palsu', array $attributes = []): Product
    {
        return Product::create([
            'user_id' => $this->seller->id, 'title' => $title, 'price' => 75000, 'image_url' => 'products/x.jpg',
            'kategori' => 'Fashion', 'status' => 'Available', ...$attributes,
        ]);
    }

    private function takeDown(Product $product, string $reason = 'Foto tidak sesuai barang asli'): void
    {
        $this->actingAs($this->admin)->patch(route('admin.products.takeDown', $product), ['reason' => $reason, 'product_id' => $product->id])
            ->assertSessionHas('success');
    }

    public function test_admin_lists_products_and_can_take_one_down_with_reason(): void
    {
        $product = $this->product();

        $this->actingAs($this->admin)->get(route('admin.products'))->assertOk()
            ->assertSee('Jaket Palsu')
            ->assertSee('Lapak Tiban')
            ->assertSee('Turunkan');

        $this->takeDown($product);

        $product->refresh();
        $this->assertTrue($product->isTakenDown());
        $this->assertSame('Foto tidak sesuai barang asli', $product->takedown_reason);

        $this->actingAs($this->admin)->get(route('admin.products', ['status' => 'diturunkan']))->assertOk()
            ->assertSee('Jaket Palsu')
            ->assertSee('Foto tidak sesuai barang asli')
            ->assertSee('Tayangkan');
    }

    public function test_reason_is_required(): void
    {
        $product = $this->product();

        $this->actingAs($this->admin)->patch(route('admin.products.takeDown', $product), ['reason' => '', 'product_id' => $product->id])
            ->assertSessionHasErrorsIn('takedown', 'reason');

        $this->assertFalse($product->fresh()->isTakenDown());
    }

    public function test_taken_down_product_is_hidden_from_catalog_and_cannot_be_bought(): void
    {
        $product = $this->product('Jaket Palsu', ['shipping_options' => [['courier' => 'JNE', 'cost' => 10000]]]);
        $this->product('Sepatu Aman');
        $this->takeDown($product);
        $this->flushSession();

        $this->actingAs($this->buyer)->get('/')->assertOk()->assertDontSee('Jaket Palsu')->assertSee('Sepatu Aman');
        $this->actingAs($this->buyer)->get(route('product.show', $product))->assertNotFound();

        $this->actingAs($this->buyer)->post(route('product.book', $product->id), ['idempotency_key' => $this->formKey(), 
            'lokasi_cod' => 'Tiban Center', 'waktu_cod' => now()->addDay()->toDateTimeString(), 'qris_paid' => 1,
        ])->assertSessionHas('error');
        $this->assertSame(0, Booking::count());

        $this->actingAs($this->buyer)->post(route('order.store', $product), ['idempotency_key' => $this->formKey(), 'shipping_option' => 0, 'shipping_address' => 'Pekanbaru'])
            ->assertSessionHas('error');
        $this->assertSame(0, Order::count());
        $this->assertSame('Available', $product->fresh()->status);
    }

    public function test_seller_still_sees_taken_down_product_with_reason(): void
    {
        $product = $this->product();
        $this->takeDown($product, 'Barang termasuk kategori terlarang');

        $this->actingAs($this->seller)->get(route('dashboard'))->assertOk()
            ->assertSee('Jaket Palsu')
            ->assertSee('Diturunkan admin')
            ->assertSee('Barang termasuk kategori terlarang');

        $this->actingAs($this->seller)->get(route('product.show', $product))->assertOk()
            ->assertSee('Barang ini diturunkan admin')
            ->assertSee('Barang termasuk kategori terlarang');
    }

    public function test_taken_down_product_does_not_use_free_quota(): void
    {
        $product = $this->product();
        $this->assertSame(1, $this->seller->activeProductCount());

        $this->takeDown($product);

        $this->assertSame(0, $this->seller->fresh()->activeProductCount());
    }

    public function test_admin_can_restore_product(): void
    {
        $product = $this->product();
        $this->takeDown($product);

        $this->actingAs($this->admin)->patch(route('admin.products.restore', $product))->assertSessionHas('success');

        $product->refresh();
        $this->assertFalse($product->isTakenDown());
        $this->assertNull($product->takedown_reason);
        $this->get('/')->assertSee('Jaket Palsu');
    }
}

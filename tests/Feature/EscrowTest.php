<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EscrowTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;
    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seller = User::factory()->create(['role' => 'penjual', 'seller_status' => 'verified', 'nama_toko' => 'Jakarta Retro', 'lokasi_lapak' => 'Luar Batam']);
        $this->buyer = User::factory()->create(['name' => 'Budi', 'role' => 'pembeli']);
    }

    // Harga Rp150.000 + ongkir Rp15.000 = total Rp165.000, sudah ditandai dikirim
    private function shippedOrder(string $shippedAgo = '1 hour'): Order
    {
        $product = Product::create([
            'user_id' => $this->seller->id,
            'title' => 'Sepatu Jakarta',
            'price' => 150000,
            'image_url' => 'products/barang.jpg',
            'status' => 'Available',
            'shipping_options' => [['courier' => 'JNE Reguler', 'cost' => 15000]],
        ]);

        $this->actingAs($this->buyer)->post(route('order.store', $product), ['idempotency_key' => $this->formKey(), 'shipping_option' => 0, 'shipping_address' => 'Pekanbaru']);
        $order = Order::sole();
        $this->actingAs($this->seller)->patch(route('order.ship', $order));
        $order->forceFill(['shipped_at' => now()->sub($shippedAgo)])->save();

        return $order->fresh();
    }

    public function test_buyer_confirmation_releases_funds_minus_platform_fee(): void
    {
        $order = $this->shippedOrder();
        $this->assertFalse($order->isReleased());

        $this->actingAs($this->buyer)->patch(route('order.confirm', $order))->assertSessionHas('success');

        $order->refresh();
        $this->assertSame(Order::COMPLETED, $order->status);
        $this->assertSame(Order::RELEASED_BY_BUYER, $order->release_method);
        $this->assertSame(3000, $order->biaya_platform);
        $this->assertSame(162000, $order->sellerPayout());
        $this->assertSame(165000, (int) $order->total_price);
    }

    public function test_funds_are_released_only_once(): void
    {
        $order = $this->shippedOrder();
        $this->actingAs($this->buyer)->patch(route('order.confirm', $order));
        $releasedAt = $order->fresh()->released_at;

        $this->actingAs($this->buyer)->patch(route('order.confirm', $order))->assertSessionHas('error');
        $this->assertSame(0, Order::releaseOverdue());
        $this->assertEquals($releasedAt, $order->fresh()->released_at);
    }

    public function test_only_buyer_can_confirm_and_only_after_shipping(): void
    {
        $order = $this->shippedOrder();
        $this->actingAs($this->seller)->patch(route('order.confirm', $order))->assertForbidden();

        $order->forceFill(['status' => Order::AWAITING_SHIPMENT, 'shipped_at' => null])->save();
        $this->actingAs($this->buyer)->patch(route('order.confirm', $order))->assertSessionHas('error');
        $this->assertNull($order->fresh()->released_at);
    }

    public function test_auto_release_after_three_days_also_deducts_fee(): void
    {
        $recent = $this->shippedOrder('2 days');
        $this->assertSame(0, Order::releaseOverdue());

        $recent->forceFill(['shipped_at' => now()->subDays(3)->subMinute()])->save();
        $this->artisan('thriftlo:release-escrow')->assertSuccessful();

        $recent->refresh();
        $this->assertSame(Order::RELEASED_AUTOMATICALLY, $recent->release_method);
        $this->assertSame(3000, $recent->biaya_platform);
        $this->assertSame(162000, $recent->sellerPayout());
    }

    public function test_seller_dashboard_shows_payout_breakdown(): void
    {
        $order = $this->shippedOrder();
        $this->actingAs($this->seller)->get('/dashboard')->assertOk()
            ->assertSee('Dana ditahan thriftLO')
            ->assertDontSee('Dana sudah cair ke kamu');

        $this->actingAs($this->buyer)->patch(route('order.confirm', $order));

        $this->actingAs($this->seller)->get('/dashboard')->assertOk()
            ->assertSee('Dana sudah cair ke kamu')
            ->assertSee('Total transaksi')
            ->assertSee('Rp165.000')
            ->assertSee('Komisi platform')
            ->assertSee('Rp3.000')
            ->assertSee('Diterima bersih')
            ->assertSee('Rp162.000');
    }

    public function test_dashboard_visit_auto_releases_overdue_orders(): void
    {
        $order = $this->shippedOrder('4 days');

        $this->actingAs($this->seller)->get('/dashboard')->assertOk()->assertSee('Dana sudah cair ke kamu');
        $this->assertSame(Order::RELEASED_AUTOMATICALLY, $order->fresh()->release_method);
    }

    public function test_buyer_sees_escrow_explanation_without_commission(): void
    {
        $order = $this->shippedOrder();
        $available = $order->product->replicate()->fill(['status' => 'Available']);
        $available->save();

        $this->actingAs($this->buyer)->get(route('product.show', $available))->assertOk()
            ->assertSee('Pembayaran aman')
            ->assertDontSee('Komisi')
            ->assertDontSee('komisi');

        $this->actingAs($this->buyer)->get(route('bookings.index'))->assertOk()
            ->assertSee('Barang Sudah Diterima')
            ->assertDontSee('Komisi');
    }

    public function test_admin_sees_total_kirim_commission(): void
    {
        $order = $this->shippedOrder();
        $this->actingAs($this->buyer)->patch(route('order.confirm', $order));
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/admin/sellers')->assertOk()
            ->assertSee('Pendapatan komisi Kirim')
            ->assertSee('dari 1 pesanan')
            ->assertSee('Rp3.000');
    }
}

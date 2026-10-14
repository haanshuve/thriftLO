<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Tests\TestCase;

class AdminShoppingRestrictionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin', 'email' => 'admin@thriftlo.id']);
        // Penjual di Batam (bisa COD) yang juga menyediakan pengiriman
        $this->seller = User::factory()->create(['role' => 'penjual', 'seller_status' => 'verified', 'lokasi_lapak' => 'Nagoya']);
    }

    private function product(): Product
    {
        return Product::create([
            'user_id' => $this->seller->id, 'title' => 'Jaket Kulit', 'price' => 100000, 'image_url' => 'products/x.jpg',
            'status' => 'Available', 'shipping_options' => [['courier' => 'JNE', 'cost' => 15000]],
        ]);
    }

    // --- Arah setelah login ---

    public function test_admin_is_redirected_to_admin_panel_after_password_login(): void
    {
        $this->post('/login', ['email' => 'admin@thriftlo.id', 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard', absolute: false))
            ->assertSessionHas('success', 'Selamat datang di Panel Admin.');

        $this->assertAuthenticatedAs($this->admin);
    }

    public function test_admin_is_redirected_to_admin_panel_after_google_login(): void
    {
        $googleUser = (new GoogleUser)
            ->setRaw(['email_verified' => true])
            ->map(['id' => 'google-admin', 'name' => 'Admin', 'email' => 'admin@thriftlo.id']);
        Socialite::shouldReceive('driver->user')->andReturn($googleUser);

        $this->get('/auth/google/callback')
            ->assertRedirect(route('admin.dashboard', absolute: false))
            ->assertSessionHas('success', 'Selamat datang di Panel Admin.');

        $this->assertAuthenticatedAs($this->admin);
    }

    public function test_buyer_and_seller_login_redirects_are_unchanged(): void
    {
        $buyer = User::factory()->create(['role' => 'pembeli']);

        $this->post('/login', ['email' => $buyer->email, 'password' => 'password'])
            ->assertRedirect(route('home', absolute: false))
            ->assertSessionHas('success', 'Berhasil masuk! Silakan lanjutkan booking barang favoritmu.');
        $this->post('/logout');

        $this->post('/login', ['email' => $this->seller->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard', absolute: false))
            ->assertSessionHas('success', 'Selamat datang kembali di Dashboard Penjual!');
    }

    // --- Tombol beli disembunyikan ---

    public function test_catalog_and_detail_hide_buy_buttons_for_admin_but_keep_chat(): void
    {
        $product = $this->product();

        $this->actingAs($this->admin)->get('/')->assertOk()
            ->assertSee('Jaket Kulit')
            ->assertSee('Lihat detail')
            ->assertSee('Tanya penjual')
            ->assertSee('Panel Admin')
            ->assertDontSee('openBookingModal(this)', false);

        $this->actingAs($this->admin)->get(route('product.show', $product))->assertOk()
            ->assertSee('tidak bisa booking atau checkout')
            ->assertSee(route('chat.index', ['user_id' => $this->seller->id, 'product_id' => $product->id]))
            ->assertDontSee('openBookingModal(this)', false)
            ->assertDontSee(route('order.store', $product), false);
    }

    public function test_buyer_still_sees_buy_buttons(): void
    {
        $product = $this->product();
        $buyer = User::factory()->create(['role' => 'pembeli']);

        $this->actingAs($buyer)->get('/')->assertOk()->assertSee('openBookingModal(this)', false);
        $this->actingAs($buyer)->get(route('product.show', $product))->assertOk()
            ->assertSee('openBookingModal(this)', false)
            ->assertSee(route('order.store', $product), false);
    }

    // --- Ditolak di server ---

    public function test_admin_booking_request_is_rejected(): void
    {
        $product = $this->product();

        $this->actingAs($this->admin)->post(route('product.book', $product->id), ['idempotency_key' => $this->formKey(), 
            'lokasi_cod' => 'Nagoya Hill', 'waktu_cod' => now()->addDay()->toDateTimeString(), 'qris_paid' => 1,
        ])->assertRedirect(route('product.show', $product))
            ->assertSessionHas('error', User::ADMIN_CANNOT_BUY_MESSAGE);

        $this->assertSame(0, Booking::count());
        $this->assertSame('Available', $product->fresh()->status);
    }

    public function test_admin_checkout_request_is_rejected(): void
    {
        $product = $this->product();

        $this->actingAs($this->admin)->post(route('order.store', $product), ['idempotency_key' => $this->formKey(), 'shipping_option' => 0, 'shipping_address' => 'Pekanbaru'])
            ->assertRedirect(route('product.show', $product))
            ->assertSessionHas('error', User::ADMIN_CANNOT_BUY_MESSAGE);

        $this->assertSame(0, Order::count());
        $this->assertSame('Available', $product->fresh()->status);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    private const PAGES = ['admin.dashboard', 'admin.sellers', 'admin.users', 'admin.products', 'admin.transactions', 'admin.categories', 'admin.brands', 'admin.reports'];

    /** @return list<array{string, string}> [method, url] semua rute admin, dengan parameter berisi data asli */
    private function adminRoutes(): array
    {
        $seller = User::factory()->create(['role' => 'penjual', 'seller_status' => 'pending']);
        $product = Product::create(['user_id' => $seller->id, 'title' => 'Barang', 'price' => 1000, 'image_url' => 'p.jpg', 'status' => 'Available']);
        $params = ['id' => $seller->id, 'user' => $seller->id, 'product' => $product->id, 'category' => Category::first()->id, 'brand' => Brand::first()->id];

        $routes = collect(Route::getRoutes()->getRoutesByName())
            ->filter(fn ($route, $name) => str_starts_with($name, 'admin.'))
            ->map(fn ($route, $name) => [
                collect($route->methods())->reject(fn ($m) => $m === 'HEAD')->first(),
                route($name, array_intersect_key($params, array_flip($route->parameterNames()))),
            ])
            ->values()
            ->all();

        $this->assertGreaterThanOrEqual(22, count($routes), 'Semua rute admin harus ikut diuji');

        return $routes;
    }

    public function test_every_admin_route_uses_admin_middleware(): void
    {
        foreach (Route::getRoutes()->getRoutesByName() as $name => $route) {
            if (str_starts_with($name, 'admin.')) {
                $this->assertContains('admin', $route->gatherMiddleware(), "Rute {$name} belum dilindungi middleware admin");
            }
        }
    }

    public function test_buyer_and_seller_are_forbidden_from_every_admin_route(): void
    {
        $routes = $this->adminRoutes();

        foreach (['pembeli', 'penjual'] as $role) {
            $user = User::factory()->create(['role' => $role, 'seller_status' => 'verified']);

            foreach ($routes as [$method, $url]) {
                $this->actingAs($user)->call($method, $url)->assertForbidden();
            }
        }
    }

    public function test_guest_is_redirected_to_login(): void
    {
        foreach ($this->adminRoutes() as [$method, $url]) {
            $this->call($method, $url)->assertRedirect('/login');
        }
    }

    public function test_admin_can_open_every_admin_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        foreach (self::PAGES as $name) {
            $this->actingAs($admin)->get(route($name))->assertOk()
                ->assertSee('Navigasi admin', false)
                ->assertSee('aria-current="page"', false)
                ->assertDontSee('Navigasi bawah', false);
        }
    }

    public function test_dashboard_shows_counts_and_action_items(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->count(2)->create(['role' => 'pembeli']);
        $seller = User::factory()->create(['role' => 'penjual', 'seller_status' => 'verified']);
        User::factory()->create(['role' => 'penjual', 'seller_status' => 'pending', 'nama_toko' => 'Toko Antre']);

        $product = Product::create(['user_id' => $seller->id, 'title' => 'Barang', 'price' => 50000, 'image_url' => 'p.jpg', 'status' => 'Booked']);
        Booking::create(['product_id' => $product->id, 'user_id' => $admin->id, 'qr_token' => 'TL-1', 'cod_location' => 'Nagoya', 'cod_schedule' => now(), 'status_cod' => 'Cancelled', 'booking_fee' => 2000, 'fee_status' => Booking::FEE_FORFEITED]);
        Booking::create(['product_id' => $product->id, 'user_id' => $admin->id, 'qr_token' => 'TL-2', 'cod_location' => 'Nagoya', 'cod_schedule' => now(), 'status_cod' => 'Cancelled', 'booking_fee' => 2000, 'fee_status' => Booking::FEE_REFUNDED]);
        $order = Order::create(['product_id' => $product->id, 'user_id' => $admin->id, 'courier' => 'JNE', 'shipping_cost' => 0, 'item_price' => 50000, 'total_price' => 50000, 'shipping_address' => 'Medan', 'status' => Order::COMPLETED]);
        $order->forceFill(['released_at' => now(), 'biaya_platform' => 3000])->save();

        $html = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()
            ->assertSee('Perlu tindakan')
            ->assertSee('Toko Antre')
            ->assertSee('Rp3.000')   // komisi Kirim
            ->assertSee('Rp2.000')   // biaya booking (yang dikembalikan tidak dihitung)
            ->getContent();

        $this->assertMatchesRegularExpression('/Total akun<\/p>\s*<p[^>]*>5</', $html);
        $this->assertMatchesRegularExpression('/Pembatalan COD<\/p>\s*<p[^>]*>2</', $html);
        $this->assertStringContainsString('href="' . route('admin.sellers') . '"', $html);
    }

    public function test_reports_show_daily_and_monthly_activity(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create(['role' => 'pembeli']);
        User::factory()->create(['role' => 'pembeli', 'created_at' => now()->subMonths(2)]);

        $daily = $this->actingAs($admin)->get(route('admin.reports'))->assertOk()
            ->assertSee('Rincian per hari')
            ->assertSee(now()->locale('id')->translatedFormat('d M Y'))
            ->getContent();
        $this->assertMatchesRegularExpression('/Pengguna baru<\/p>\s*<p[^>]*>1</', $daily);

        $monthly = $this->actingAs($admin)->get(route('admin.reports', ['periode' => 'bulanan']))->assertOk()
            ->assertSee('Rincian per bulan')
            ->assertSee(now()->subMonths(2)->locale('id')->translatedFormat('F Y'))
            ->getContent();
        $this->assertMatchesRegularExpression('/Pengguna baru<\/p>\s*<p[^>]*>2</', $monthly);
    }
}

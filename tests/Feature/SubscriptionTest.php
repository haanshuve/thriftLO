<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seller = User::factory()->create([
            'role' => 'penjual', 'seller_status' => 'verified', 'nama_toko' => 'Batam Vintage Hub', 'lokasi_lapak' => 'Batam Center',
        ]);
    }

    private function products(int $count, string $status = 'Available', ?User $owner = null): void
    {
        for ($i = 1; $i <= $count; $i++) {
            Product::create([
                'user_id' => ($owner ?? $this->seller)->id,
                'title' => "Barang {$status} {$i}",
                'price' => 50000,
                'image_url' => 'products/barang.jpg',
                'status' => $status,
            ]);
        }
    }

    private function upload(string $title = 'Jaket Baru')
    {
        Storage::fake('public');

        return $this->actingAs($this->seller)->post(route('product.store'), [
            'nama_barang' => $title,
            'mode_jual' => 'ecer',
            'kategori' => 'Fashion',
            'harga' => 75000,
            'grade' => config('thriftlo.grades')[0],
            'image' => UploadedFile::fake()->image('jaket.jpg'),
        ]);
    }

    public function test_free_seller_can_list_fifth_product_but_not_sixth(): void
    {
        $this->products(4);

        $this->upload('Barang Kelima')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('products', ['title' => 'Barang Kelima']);

        $this->upload('Barang Keenam')
            ->assertRedirect(route('subscription.show'))
            ->assertSessionHas('limit_reached', true);
        $this->assertDatabaseMissing('products', ['title' => 'Barang Keenam']);

        $this->actingAs($this->seller)->get(route('subscription.show'))->assertOk()
            ->assertSee('Kuota 5 produk gratis kamu sudah penuh')
            ->assertSee('Bayar Sekarang')
            ->assertSee('Rp5.000');
    }

    public function test_sold_products_do_not_count_toward_free_quota(): void
    {
        $this->products(4);
        $this->products(3, 'Sold Out');

        $this->upload('Barang Kelima')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('products', ['title' => 'Barang Kelima']);
    }

    public function test_dashboard_sends_seller_at_limit_to_subscription_page(): void
    {
        $this->products(5);

        $this->actingAs($this->seller)->get('/dashboard')->assertOk()
            ->assertSee('Paket Gratis · 5/5 produk aktif')
            ->assertSee('href="' . route('subscription.show') . '" class="shrink-0', false);
    }

    public function test_simulated_payment_activates_thirty_days(): void
    {
        $this->travelTo(now()->startOfMinute());

        $this->actingAs($this->seller)->post(route('subscription.pay'))
            ->assertRedirect(route('subscription.show'))
            ->assertSessionHas('success');

        $seller = $this->seller->fresh();
        $this->assertTrue($seller->hasActiveSubscription());
        $this->assertTrue($seller->subscription_expires_at->equalTo(now()->addDays(30)));
        $this->assertSame(30, $seller->subscriptionDaysLeft());
    }

    public function test_renewing_extends_from_current_expiry(): void
    {
        $this->travelTo(now()->startOfMinute());
        $this->seller->forceFill(['subscription_expires_at' => now()->addDays(10)])->save();

        $this->actingAs($this->seller)->post(route('subscription.pay'));

        $this->assertTrue($this->seller->fresh()->subscription_expires_at->equalTo(now()->addDays(40)));
    }

    public function test_subscribed_seller_can_list_beyond_free_quota(): void
    {
        $this->products(5);
        $this->actingAs($this->seller)->post(route('subscription.pay'));

        $this->upload('Barang Keenam')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('products', ['title' => 'Barang Keenam']);
    }

    public function test_payment_is_refused_when_simulation_is_off(): void
    {
        config(['thriftlo.subscription.simulate_payment' => false]);

        $this->actingAs($this->seller)->post(route('subscription.pay'))
            ->assertSessionHas('error');

        $this->assertNull($this->seller->fresh()->subscription_expires_at);
    }

    public function test_only_verified_sellers_can_subscribe(): void
    {
        $pending = User::factory()->create(['role' => 'penjual', 'seller_status' => 'pending']);
        $buyer = User::factory()->create(['role' => 'pembeli']);

        $this->actingAs($pending)->post(route('subscription.pay'))->assertSessionHas('error');
        $this->assertNull($pending->fresh()->subscription_expires_at);

        $this->actingAs($buyer)->post(route('subscription.pay'))->assertForbidden();
        $this->actingAs($buyer)->get(route('subscription.show'))->assertRedirect(route('dashboard'));
    }

    public function test_expired_subscription_hides_sixth_product_onwards_without_deleting(): void
    {
        $this->products(7);
        $this->seller->forceFill(['subscription_expires_at' => now()->subDay()])->save();

        $this->get('/')->assertOk()
            ->assertSee('Barang Available 5')
            ->assertDontSee('Barang Available 6')
            ->assertDontSee('Barang Available 7');
        $this->assertSame(7, Product::count());

        $this->actingAs($this->seller)->get('/dashboard')->assertOk()
            ->assertSee('2 produk disembunyikan dari katalog')
            ->assertSee('Disembunyikan');

        // Berlangganan lagi: semua produk tampil kembali
        $this->actingAs($this->seller)->post(route('subscription.pay'));
        $this->get('/')->assertSee('Barang Available 6')->assertSee('Barang Available 7');
    }

    public function test_other_sellers_quota_is_counted_separately(): void
    {
        $other = User::factory()->create(['role' => 'penjual', 'seller_status' => 'verified']);
        $this->products(5, 'Available', $other);
        $this->products(2);

        $this->get('/')->assertSee('Barang Available 1')->assertSee('Barang Available 2');
        $this->assertSame(7, Product::visibleInCatalog()->count());
    }

    public function test_hidden_product_cannot_be_booked_directly(): void
    {
        $this->products(6);
        $hidden = Product::where('title', 'Barang Available 6')->first();
        $buyer = User::factory()->create(['role' => 'pembeli']);

        $this->actingAs($buyer)->from('/')->post(route('product.book', $hidden->id), [
            'lokasi_cod' => 'Mega Mall', 'waktu_cod' => now()->addDay()->format('Y-m-d\TH:i'),
        ])->assertSessionHas('error');

        $this->assertSame(0, Booking::count());
        $this->assertSame('Available', $hidden->fresh()->status);
    }

    public function test_dashboard_and_profile_show_days_left(): void
    {
        $this->seller->forceFill(['subscription_expires_at' => now()->addDays(12)->addHour()])->save();

        $this->actingAs($this->seller)->get('/dashboard')->assertOk()
            ->assertSee('Langganan Unlimited · sisa 13 hari');
        $this->actingAs($this->seller)->get('/profile')->assertOk()
            ->assertSee('Langganan Unlimited · sisa 13 hari');
    }
}

<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DemoProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HomepageTest extends TestCase
{
    use RefreshDatabase;

    private User $batamSeller;
    private User $jakartaSeller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->batamSeller = User::factory()->create(['role' => 'penjual', 'seller_status' => 'verified', 'nama_toko' => 'Lapak Nagoya', 'lokasi_lapak' => 'Nagoya']);
        $this->jakartaSeller = User::factory()->create(['role' => 'penjual', 'seller_status' => 'verified', 'nama_toko' => 'Jakarta Retro', 'lokasi_lapak' => 'Luar Batam', 'kota_lapak' => 'Jakarta']);
    }

    private function product(User $seller, string $title, array $attributes = []): Product
    {
        return Product::create([
            'user_id' => $seller->id, 'title' => $title, 'price' => 100000, 'image_url' => 'products/x.jpg',
            'kategori' => 'Fashion', 'status' => 'Available', ...$attributes,
        ]);
    }

    // Potongan HTML satu section koleksi di beranda
    private function collectionSection(string $html, string $key): ?string
    {
        $start = strpos($html, 'data-collection="' . $key . '"');
        if ($start === false) {
            return null;
        }
        $end = strpos($html, '</section>', $start);

        return substr($html, $start, $end - $start);
    }

    public function test_home_shows_carousel_banners_perks_steps_seller_banner_and_footer(): void
    {
        $html = $this->get('/')->assertOk()
            // Carousel 4 slide dari fitur nyata + navigasi yang aksesibel
            ->assertSee('aria-roledescription="carousel"', false)
            ->assertSee('Lihat kondisi asli lewat Video Proof')
            ->assertSee('COD aman pakai Smart QR')
            ->assertSee('Belanja dari seluruh Indonesia')
            ->assertSee('Punya barang nganggur? Jadi penjual')
            ->assertSee('aria-label="Slide berikutnya"', false)
            ->assertSee('aria-label="Tampilkan slide 4"', false)
            ->assertSee('prefers-reduced-motion', false)
            // Banner samping
            ->assertSee('Request barang')
            ->assertSee('Jadi penjual terverifikasi')
            ->assertSee(route('requests.index'), false)
            // Strip keunggulan & cara kerja
            ->assertSee('Penjual terverifikasi KTP')
            ->assertSee('Dana aman dengan Escrow')
            ->assertSee('Cara kerja thriftLO')
            ->assertSee('Terima barang dan konfirmasi')
            // Footer
            ->assertSee('Bantuan singkat')
            ->assertSee('tidak dapat dikembalikan dan menjadi milik platform, baik kamu maupun penjual yang membatalkan')
            ->getContent();

        $this->assertSame(4, substr_count($html, 'aria-roledescription="slide"'));
        // Tanpa gambar eksternal di hero
        $hero = substr($html, strpos($html, 'Sorotan thriftLO'), strpos($html, 'judulKeunggulan') - strpos($html, 'Sorotan thriftLO'));
        $this->assertStringNotContainsString('<img', $hero);
    }

    public function test_seller_banner_uses_numbers_from_config(): void
    {
        config(['thriftlo.subscription.free_product_limit' => 7, 'thriftlo.subscription.price' => 12500, 'thriftlo.subscription.days' => 30]);

        $this->get('/')->assertOk()
            ->assertSee('Gratis 7 produk tayang')
            ->assertSee('Langganan Rp12.500 per 30 hari')
            ->assertSee(route('register', ['role' => 'penjual']), false);
    }

    public function test_seller_cta_points_sellers_to_their_shop(): void
    {
        $this->actingAs($this->batamSeller)->get('/')->assertOk()
            ->assertSee('Buka Toko Saya')
            ->assertDontSee(route('register', ['role' => 'penjual']), false);
    }

    public function test_register_preselects_seller_role_from_cta(): void
    {
        $this->get(route('register', ['role' => 'penjual']))->assertOk()
            ->assertSee('id="rolePenjual" checked', false);
        $this->get(route('register'))->assertOk()
            ->assertSee('id="rolePembeli" checked', false);
    }

    public function test_stats_are_real_and_hidden_when_zero(): void
    {
        // Tanpa transaksi selesai: hanya statistik yang tidak nol yang tampil
        $this->product($this->batamSeller, 'Jaket A');

        $this->get('/')->assertOk()
            ->assertSee('barang tayang')
            ->assertSee('penjual terverifikasi')
            ->assertDontSee('transaksi selesai')
            ->assertDontSee('kg</span>', false); // estimasi "barang terselamatkan" lama sudah dihapus

        $product = $this->product($this->batamSeller, 'Jaket B');
        Booking::create(['product_id' => $product->id, 'user_id' => User::factory()->create()->id, 'qr_token' => 'TL-SELESAI', 'cod_location' => 'Nagoya', 'cod_schedule' => now(), 'status_cod' => 'Completed']);

        $this->get('/')->assertOk()->assertSeeInOrder(['1', 'transaksi selesai']);
    }

    public function test_stats_row_hidden_when_everything_is_zero(): void
    {
        User::query()->update(['seller_status' => 'pending']);

        $this->get('/')->assertOk()->assertDontSee('aria-label="Statistik thriftLO"', false);
    }

    public function test_themed_sections_only_show_when_they_have_products(): void
    {
        $this->product($this->batamSeller, 'Tas COD Saja');
        $this->product($this->jakartaSeller, 'Sepatu Kirim', ['shipping_options' => [['courier' => 'JNE', 'cost' => 15000]]]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('Tas COD Saja', $this->collectionSection($html, 'baru'));
        $this->assertStringContainsString('Sepatu Kirim', $this->collectionSection($html, 'baru'));
        $this->assertStringContainsString('Tas COD Saja', $this->collectionSection($html, 'cod'));
        $this->assertStringNotContainsString('Sepatu Kirim', $this->collectionSection($html, 'cod'));
        $this->assertStringContainsString('Sepatu Kirim', $this->collectionSection($html, 'kirim'));
        $this->assertStringNotContainsString('Tas COD Saja', $this->collectionSection($html, 'kirim'));
        $this->assertNull($this->collectionSection($html, 'video'), 'Section tanpa barang tidak tampil');

        $this->assertStringContainsString(e(route('home', ['koleksi' => 'kirim'])), $html);
    }

    public function test_home_shows_empty_state_instead_of_sections_when_catalog_is_empty(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('Katalog lagi kosong nih')
            ->assertDontSee('data-collection=', false);
    }

    public function test_collection_link_lists_all_matching_products_and_keeps_other_filters(): void
    {
        $this->product($this->batamSeller, 'Tas COD Saja', ['kategori' => 'Tas']);
        $this->product($this->batamSeller, 'Jaket COD', ['kategori' => 'Fashion']);
        $this->product($this->jakartaSeller, 'Sepatu Kirim', ['shipping_options' => [['courier' => 'JNE', 'cost' => 15000]]]);
        $this->product($this->batamSeller, 'Jaket Sudah Dibooking', ['status' => 'Booked']);

        $this->get(route('home', ['koleksi' => 'cod']))->assertOk()
            ->assertSee('Siap COD di Batam')
            ->assertSee('Tas COD Saja')
            ->assertSee('Jaket COD')
            ->assertDontSee('Sepatu Kirim')
            ->assertDontSee('Jaket Sudah Dibooking')
            ->assertSee('🛍️ Semua'); // halaman katalog: chip kategori tetap ada

        $this->get(route('home', ['koleksi' => 'cod', 'kategori' => 'Tas']))->assertOk()
            ->assertSee('Tas COD Saja')
            ->assertDontSee('Jaket COD');

        // Koleksi "baru" = semua barang tayang, termasuk yang sedang di-booking
        $this->get(route('home', ['koleksi' => 'baru']))->assertOk()->assertSee('Jaket Sudah Dibooking');
        // Nilai koleksi tak dikenal diabaikan (kembali ke beranda biasa)
        $this->get(route('home', ['koleksi' => 'ngawur']))->assertOk()->assertSee('aria-roledescription="carousel"', false);
    }

    public function test_bottom_nav_category_anchor_points_to_grid_on_home(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('id="kategori"', false)
            ->assertSee('href="#kategori"', false);
    }

    public function test_home_has_no_fake_marketing_numbers(): void
    {
        $this->product($this->batamSeller, 'Jaket A');

        $this->get('/')->assertOk()
            ->assertDontSee('Diskon')
            ->assertDontSee('terjual')
            ->assertDontSee('rating');
    }

    // ---------------------------------------------------------------- Data contoh (lokal)

    public function test_demo_seeder_adds_marked_branded_products_and_demo_clear_removes_them(): void
    {
        Storage::fake('public');
        $real = $this->product($this->batamSeller, 'Barang Asli');

        $this->seed(DemoProductSeeder::class);

        $demo = Product::where('title', 'like', DemoProductSeeder::TITLE_PREFIX . '%')->get();
        $this->assertCount(8, $demo);
        $this->assertTrue($demo->every(fn ($p) => str_contains($p->description, DemoProductSeeder::MARKER)));
        $this->assertSame(['Fashion', 'Sepatu', 'Tas', 'Vintage Tech'], $demo->pluck('kategori')->unique()->sort()->values()->all());
        $this->assertSame(8, $demo->whereNotNull('brand_id')->count());
        $this->assertSame(8, $demo->pluck('brand_id')->unique()->count());
        // Penjual luar Batam selalu punya opsi kirim supaya barangnya bisa dibeli
        $this->assertTrue($demo->where('user_id', $this->jakartaSeller->id)->every(fn ($p) => !empty($p->shipping_options)));
        Storage::disk('public')->assertExists($demo->first()->image_url);

        // Menjalankan ulang tidak menggandakan data
        $this->seed(DemoProductSeeder::class);
        $this->assertSame(8, Product::where('title', 'like', DemoProductSeeder::TITLE_PREFIX . '%')->count());

        $this->artisan('demo:clear', ['--force' => true])->expectsOutputToContain('8 produk contoh dihapus')->assertSuccessful();

        $this->assertSame(0, Product::where('title', 'like', DemoProductSeeder::TITLE_PREFIX . '%')->count());
        $this->assertModelExists($real);
        Storage::disk('public')->assertMissing(DemoProductSeeder::IMAGE_DIR . '/sepatu-nike-air-max-90.svg');
    }

    public function test_demo_seeder_is_not_called_from_database_seeder(): void
    {
        $this->assertStringNotContainsString('DemoProductSeeder', file_get_contents(database_path('seeders/DatabaseSeeder.php')));
    }

    public function test_demo_seeder_refuses_to_run_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        // Dipanggil langsung (db:seed sendiri juga meminta konfirmasi di production)
        $this->app->make(DemoProductSeeder::class)->run();

        $this->assertSame(0, Product::where('title', 'like', DemoProductSeeder::TITLE_PREFIX . '%')->count());
    }
}

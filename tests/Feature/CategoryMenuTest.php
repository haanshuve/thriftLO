<?php

namespace Tests\Feature;

use App\Http\Controllers\ProductController;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Support\CategoryMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CategoryMenuTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seller = User::factory()->create(['role' => 'penjual', 'seller_status' => 'verified', 'lokasi_lapak' => 'Nagoya']);
    }

    private function product(string $kategori, ?Brand $brand = null, array $attributes = []): Product
    {
        return Product::create([
            'user_id' => $this->seller->id, 'title' => 'Barang ' . uniqid(), 'price' => 100000, 'image_url' => 'products/x.jpg',
            'kategori' => $kategori, 'status' => 'Available', 'brand_id' => $brand?->id, ...$attributes,
        ]);
    }

    /** Semua href tautan menu (desktop + ponsel) di halaman. */
    private function menuLinks(string $html): array
    {
        preg_match_all('/<a href="([^"]+)"[^>]*data-menu-link/', $html, $m);

        return array_values(array_unique(array_map('html_entity_decode', $m[1])));
    }

    public function test_navigation_renders_on_market_pages_with_aria_attributes(): void
    {
        $html = $this->get(route('home'))->assertOk()
            ->assertSee('data-category-menu="desktop"', false)
            ->assertSee('data-category-menu="mobile"', false)
            ->assertSee('aria-haspopup="true"', false)
            ->assertSee('aria-expanded="false"', false)
            ->assertSee('aria-controls="mm-panel-fashion"', false)
            ->assertSee('aria-label="Menu kategori"', false)
            ->assertSee('Fashion')
            ->assertSee('Elektronik &amp; Hobi', false)
            ->getContent();

        // Panel kategori dan ikon SVG (bukan emoji) di kolom kiri
        $this->assertStringContainsString('id="mm-panel-fashion"', $html);
        $this->assertMatchesRegularExpression('/data-cat-link="Sepatu".*?<svg/s', $html);

        // Halaman lain juga memuat menu
        $product = $this->product('Tas');
        $this->get(route('product.show', $product))->assertOk()->assertSee('data-category-menu="desktop"', false);
    }

    public function test_every_menu_link_points_to_a_working_filtered_catalog(): void
    {
        $levis = Brand::create(['name' => "Levi's", 'is_active' => true]);
        $nike = Brand::create(['name' => 'Nike', 'is_active' => true]);
        $this->product('Fashion', $levis);
        $this->product('Sepatu', $nike);
        $this->product('Sepatu', $nike);

        $links = $this->menuLinks($this->get(route('home'))->assertOk()->getContent());
        $this->assertNotEmpty($links);

        $slugs = Category::slugs();
        foreach ($links as $url) {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $q);

            // Tidak ada kategori/merek/koleksi yang tidak ada di sistem
            $this->assertContains($q['kategori'] ?? null, $slugs, "Kategori tidak dikenal: {$url}");
            if (isset($q['merek'])) {
                $this->assertTrue(Brand::active()->where('slug', $q['merek'])->exists(), "Merek tidak dikenal: {$url}");
            }
            if (isset($q['koleksi'])) {
                $this->assertArrayHasKey($q['koleksi'], ProductController::COLLECTIONS);
            }

            $this->get($url)->assertOk();
        }

        // Subkategori merek benar-benar memfilter katalog
        $this->assertContains(route('home', ['kategori' => 'Sepatu', 'merek' => $nike->slug]), $links);
        $this->assertContains(route('home', ['kategori' => 'Fashion', 'merek' => $levis->slug]), $links);
        $this->assertNotContains(route('home', ['kategori' => 'Sepatu', 'merek' => $levis->slug]), $links);
    }

    public function test_only_categories_that_exist_in_the_system_are_shown(): void
    {
        config(['thriftlo.category_groups' => [
            'fashion' => ['label' => 'Fashion', 'categories' => ['Fashion', 'Kategori Hantu']],
            'kosong'  => ['label' => 'Grup Kosong', 'categories' => ['Tidak Ada']],
            'lainnya' => ['label' => 'Lainnya', 'categories' => ['Lainnya']],
        ]]);
        Category::create(['slug' => 'motor', 'label' => 'Motor', 'icon' => '🏍️', 'sort_order' => 99]);

        $groups = collect(CategoryMenu::groups())->keyBy('key');
        $shown = $groups->flatMap(fn ($g) => array_column($g['categories'], 'slug'))->all();

        $this->assertNotContains('Kategori Hantu', $shown);
        $this->assertFalse($groups->has('kosong'), 'Grup tanpa kategori yang ada disembunyikan');
        // Kategori dari admin yang belum dipetakan tetap muncul (di grup terakhir), tanpa yang hilang
        $this->assertSame(['Lainnya', 'Sepatu', 'Tas', 'Aksesoris', 'Vintage Tech', 'motor'], array_column($groups['lainnya']['categories'], 'slug'));
        $this->assertEqualsCanonicalizing(Category::slugs(), $shown);

        $this->get(route('home'))->assertOk()->assertDontSee('Kategori Hantu')->assertDontSee('Grup Kosong');
    }

    public function test_counts_come_from_visible_catalog_products_only(): void
    {
        $nike = Brand::create(['name' => 'Nike', 'is_active' => true]);
        $hidden = Brand::create(['name' => 'Merek Nonaktif', 'is_active' => false]);
        $this->product('Sepatu', $nike);
        $this->product('Sepatu', $nike);
        $this->product('Sepatu', $hidden);
        $this->product('Sepatu', $nike, ['status' => 'Sold Out']);          // sudah terjual: tidak tampil
        $this->product('Sepatu', $nike)->forceFill(['taken_down_at' => now()])->save(); // diturunkan admin: tidak tampil
        Cache::flush();

        $sepatu = collect(CategoryMenu::groups())->flatMap(fn ($g) => $g['categories'])->firstWhere('slug', 'Sepatu');

        $this->assertSame(3, $sepatu['count']);
        $this->assertSame([['slug' => $nike->slug, 'name' => 'Nike', 'count' => 2]], $sepatu['brands']);
    }

    public function test_menu_data_uses_one_aggregate_query_and_is_cached(): void
    {
        $this->product('Fashion', Brand::create(['name' => 'Uniqlo', 'is_active' => true]));
        Cache::flush();

        DB::enableQueryLog();
        CategoryMenu::groups();
        $productQueries = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'from "products"'));
        $this->assertCount(1, $productQueries);

        DB::flushQueryLog();
        CategoryMenu::groups();
        $this->assertCount(0, DB::getQueryLog(), 'Panggilan kedua diambil dari cache');

        // Admin mengubah kategori: cache dibuang dan label baru langsung tampil
        Category::where('slug', 'Tas')->first()->update(['label' => 'Tas & Dompet']);
        $this->assertContains('Tas & Dompet', collect(CategoryMenu::groups())->flatMap(fn ($g) => array_column($g['categories'], 'label'))->all());
    }

    public function test_full_height_chat_page_has_no_mega_menu(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'pembeli']))->get(route('chat.index'))->assertOk()
            ->assertDontSee('data-category-menu', false);
    }
}

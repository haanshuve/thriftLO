<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seller = User::factory()->create(['role' => 'penjual', 'seller_status' => 'verified', 'nama_toko' => 'Lapak Nagoya', 'lokasi_lapak' => 'Nagoya']);
    }

    private function brand(string $name): Brand
    {
        return Brand::where('name', $name)->sole();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'idempotency_key' => $this->formKey(),
            'nama_barang' => 'Sepatu Lari Bekas',
            'mode_jual'   => 'ecer',
            'kategori'    => 'Sepatu',
            'harga'       => 250000,
            'grade'       => config('thriftlo.grades')[0],
            'image'       => UploadedFile::fake()->image('sepatu.jpg'),
            'video_proof' => UploadedFile::fake()->create('sepatu.mp4', 1024, 'video/mp4'),
        ], $overrides);
    }

    private function product(string $title, ?Brand $brand = null, array $attributes = []): Product
    {
        return Product::create([
            'user_id' => $this->seller->id, 'title' => $title, 'price' => 100000, 'image_url' => 'products/x.jpg',
            'kategori' => 'Sepatu', 'status' => 'Available', 'brand_id' => $brand?->id, ...$attributes,
        ]);
    }

    // --- Data awal ---

    public function test_initial_brands_are_seeded_with_automatic_slugs(): void
    {
        $this->assertSame(25, Brand::count());
        $this->assertSame('levis', $this->brand("Levi's")->slug);
        $this->assertSame('hm', $this->brand('H&M')->slug);
        $this->assertSame('the-north-face', $this->brand('The North Face')->slug);
    }

    // --- Simpan produk ---

    public function test_seller_can_save_product_with_brand_from_list(): void
    {
        $nike = $this->brand('Nike');

        $this->actingAs($this->seller)->post(route('product.store'), $this->payload(['brand_id' => $nike->id]))
            ->assertSessionHasNoErrors();

        $product = Product::sole();
        $this->assertSame($nike->id, $product->brand_id);
        $this->assertNull($product->brand_other);
        $this->assertSame('Nike', $product->brandName());
    }

    public function test_seller_can_save_product_with_other_brand(): void
    {
        $this->actingAs($this->seller)->post(route('product.store'), $this->payload(['brand_id' => 'lainnya', 'brand_other' => '  Kickers ']))
            ->assertSessionHasNoErrors();

        $product = Product::sole();
        $this->assertNull($product->brand_id);
        $this->assertSame('Kickers', $product->brand_other);
    }

    public function test_other_brand_matching_list_is_saved_as_listed_brand(): void
    {
        $this->actingAs($this->seller)->post(route('product.store'), $this->payload(['brand_id' => 'lainnya', 'brand_other' => 'adidas']))
            ->assertSessionHasNoErrors();

        $this->assertSame($this->brand('Adidas')->id, Product::sole()->brand_id);
    }

    public function test_other_brand_requires_a_name(): void
    {
        $this->actingAs($this->seller)->post(route('product.store'), $this->payload(['brand_id' => 'lainnya', 'brand_other' => '']))
            ->assertSessionHasErrorsIn('product', ['brand_other' => 'Tulis nama mereknya, atau kosongkan pilihan merek kalau barangnya tanpa merek.']);

        $this->assertSame(0, Product::count());
    }

    public function test_unknown_or_inactive_brand_is_rejected(): void
    {
        $vans = $this->brand('Vans');
        $vans->update(['is_active' => false]);

        foreach ([999999, $vans->id] as $brandId) {
            $this->actingAs($this->seller)->post(route('product.store'), $this->payload(['brand_id' => $brandId]))
                ->assertSessionHasErrorsIn('product', 'brand_id');
        }

        $this->assertSame(0, Product::count());
    }

    public function test_product_without_brand_is_still_valid_and_can_be_edited(): void
    {
        $this->actingAs($this->seller)->post(route('product.store'), $this->payload())->assertSessionHasNoErrors();
        $product = Product::sole();
        $this->assertNull($product->brandName());

        // Produk lama tanpa merek tetap bisa diedit tanpa mengisi merek
        $this->actingAs($this->seller)->put(route('product.update', $product->id), $this->payload(['nama_barang' => 'Judul Baru', 'image' => null, 'video_proof' => null]))
            ->assertSessionHasNoErrors();
        $this->assertSame('Judul Baru', $product->fresh()->title);
    }

    public function test_editing_keeps_brand_that_was_deactivated_later(): void
    {
        $vans = $this->brand('Vans');
        $product = $this->product('Vans Old Skool', $vans, ['video_proof_path' => 'product-videos/x.mp4']);
        $vans->update(['is_active' => false]);

        $this->actingAs($this->seller)->get(route('product.edit', $product->id))->assertOk()->assertSee('Vans');
        $this->actingAs($this->seller)->put(route('product.update', $product->id), $this->payload(['brand_id' => $vans->id, 'image' => null, 'video_proof' => null]))
            ->assertSessionHasNoErrors();
        $this->assertSame($vans->id, $product->fresh()->brand_id);
    }

    public function test_sell_form_shows_brand_dropdown_with_other_option(): void
    {
        $this->actingAs($this->seller)->get(route('dashboard'))->assertOk()
            ->assertSee('Merek')
            ->assertSee('(opsional)')
            ->assertSee('Tanpa merek / tidak tahu')
            ->assertSee('Merek lain…')
            ->assertSee('name="brand_other"', false);
    }

    // --- Katalog & pencarian ---

    public function test_catalog_filters_by_brand_and_combines_with_other_filters(): void
    {
        $nike = $this->brand('Nike');
        $this->product('Sepatu Nike Air', $nike);
        $this->product('Kaos Nike Polos', $nike, ['kategori' => 'Fashion']);
        $this->product('Sepatu Adidas Samba', $this->brand('Adidas'));

        $this->get(route('home', ['merek' => 'nike']))->assertOk()
            ->assertSee('Merek Nike')
            ->assertSee('Sepatu Nike Air')
            ->assertSee('Kaos Nike Polos')
            ->assertDontSee('Sepatu Adidas Samba');

        $this->get(route('home', ['merek' => 'nike', 'kategori' => 'Fashion']))->assertOk()
            ->assertSee('Kaos Nike Polos')
            ->assertDontSee('Sepatu Nike Air');

        $this->get(route('home', ['merek' => 'tidak-ada']))->assertOk()
            ->assertDontSee('Sepatu Nike Air')
            ->assertDontSee('Sepatu Adidas Samba');
    }

    public function test_text_search_finds_products_by_brand_name(): void
    {
        $this->product('Sepatu Hitam Ukuran 42', $this->brand('Converse'));
        $this->product('Jaket Gunung', null, ['brand_other' => 'Rei']);
        $this->product('Kemeja Putih');

        $this->get(route('home', ['search' => 'converse']))->assertOk()
            ->assertSee('Sepatu Hitam Ukuran 42')
            ->assertDontSee('Kemeja Putih');

        $this->get(route('home', ['search' => 'rei']))->assertOk()
            ->assertSee('Jaket Gunung')
            ->assertDontSee('Kemeja Putih');
    }

    public function test_product_card_and_detail_show_brand_link_and_disclaimer(): void
    {
        $product = $this->product('Sepatu Lari', $this->brand('New Balance'));

        $this->get('/')->assertOk()->assertSee(route('brands.show', 'new-balance'), false);

        $this->get(route('product.show', $product))->assertOk()
            ->assertSee('New Balance')
            ->assertSee(route('brands.show', 'new-balance'), false)
            ->assertSee('Merek diisi oleh penjual. ThriftLO tidak menjamin keaslian barang.');
    }

    public function test_detail_without_brand_has_no_disclaimer(): void
    {
        $product = $this->product('Kemeja Putih');

        $this->get(route('product.show', $product))->assertOk()
            ->assertDontSee('ThriftLO tidak menjamin keaslian barang');
    }

    // --- Section beranda ---

    public function test_home_brand_section_is_hidden_when_no_branded_products(): void
    {
        $this->product('Kemeja Polos');

        $this->get('/')->assertOk()->assertDontSee('Belanja berdasarkan merek');
    }

    public function test_home_brand_section_orders_by_active_product_count_and_hides_empty_brands(): void
    {
        $adidas = $this->brand('Adidas');
        $this->product('Adidas 1', $adidas);
        $this->product('Adidas 2', $adidas);
        $this->product('Nike 1', $this->brand('Nike'));
        // Barang terjual dan diturunkan tidak dihitung
        $this->product('Puma terjual', $this->brand('Puma'), ['status' => 'Sold Out']);
        $this->product('Vans diturunkan', $this->brand('Vans'))->forceFill(['taken_down_at' => now(), 'takedown_reason' => 'Melanggar'])->save();

        $html = $this->get('/')->assertOk()
            ->assertSee('Belanja berdasarkan merek')
            ->assertSee('Lihat semua merek')
            ->assertSee('2 barang')
            ->getContent();

        $section = substr($html, strpos($html, 'id="judulMerek"'), strpos($html, 'id="judulKatalog"') - strpos($html, 'id="judulMerek"'));
        $this->assertLessThan(strpos($section, 'merek/nike'), strpos($section, 'merek/adidas'));
        $this->assertStringNotContainsString('merek/puma', $section);
        $this->assertStringNotContainsString('merek/vans', $section);
        $this->assertStringNotContainsString('merek/zara', $section);
    }

    // --- Halaman merek ---

    public function test_brand_index_lists_active_brands_with_counts(): void
    {
        $this->product('Adidas 1', $this->brand('Adidas'));
        $this->brand('Fila')->update(['is_active' => false]);

        $this->get(route('brands.index'))->assertOk()
            ->assertSee('Semua merek')
            ->assertSee('Adidas')
            ->assertSee('1 barang')
            ->assertSee('Zara')
            ->assertDontSee('Fila');
    }

    public function test_brand_page_filters_by_category_and_sorts_by_price(): void
    {
        $nike = $this->brand('Nike');
        $this->product('Nike Murah', $nike, ['price' => 50000]);
        $this->product('Nike Mahal', $nike, ['price' => 900000]);
        $this->product('Kaos Nike', $nike, ['kategori' => 'Fashion', 'price' => 70000]);
        $this->product('Adidas Lain', $this->brand('Adidas'));

        $this->get(route('brands.show', 'nike'))->assertOk()
            ->assertSee('3 barang preloved tersedia')
            ->assertSee('Nike Murah')
            ->assertDontSee('Adidas Lain');

        $this->get(route('brands.show', ['brand' => 'nike', 'urut' => 'termurah']))->assertOk()
            ->assertSeeInOrder(['Nike Murah', 'Kaos Nike', 'Nike Mahal']);

        $this->get(route('brands.show', ['brand' => 'nike', 'urut' => 'termahal']))->assertOk()
            ->assertSeeInOrder(['Nike Mahal', 'Kaos Nike', 'Nike Murah']);

        $this->get(route('brands.show', ['brand' => 'nike', 'kategori' => 'Fashion']))->assertOk()
            ->assertSee('Kaos Nike')
            ->assertDontSee('Nike Murah');
    }

    public function test_inactive_or_unknown_brand_page_is_not_found(): void
    {
        $this->brand('Fila')->update(['is_active' => false]);

        $this->get(route('brands.show', 'fila'))->assertNotFound();
        $this->get('/merek/tidak-ada')->assertNotFound();
    }

    // --- Admin ---

    public function test_brand_admin_page_is_only_for_admin(): void
    {
        $this->get(route('admin.brands'))->assertRedirect('/login');
        foreach (['pembeli', 'penjual'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get(route('admin.brands'))->assertForbidden();
            $this->actingAs(User::factory()->create(['role' => $role]))->post(route('admin.brands.store'), ['name' => 'Merek Baru'])->assertForbidden();
        }

        $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('admin.brands'))->assertOk()
            ->assertSee('Tambah merek')
            ->assertSee('Polo Ralph Lauren');
        $this->assertNull(Brand::where('name', 'Merek Baru')->first());
    }

    public function test_admin_can_add_edit_toggle_and_order_brands(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.brands.store'), ['name' => "Dr. Martens", 'is_popular' => '1'])->assertSessionHas('success');
        $brand = Brand::where('name', 'Dr. Martens')->sole();
        $this->assertSame('dr-martens', $brand->slug);
        $this->assertTrue($brand->is_popular);
        $this->assertSame(26, $brand->sort_order);

        $this->actingAs($admin)->post(route('admin.brands.store'), ['name' => 'nike'])->assertSessionHasErrorsIn('createBrand', 'name');

        $this->actingAs($admin)->put(route('admin.brands.update', $brand), ['name' => 'Doc Martens', 'sort_order' => 1])->assertSessionHas('success');
        $brand->refresh();
        $this->assertSame('doc-martens', $brand->slug);
        $this->assertSame(1, $brand->sort_order);
        $this->assertFalse($brand->is_popular);

        $this->actingAs($admin)->patch(route('admin.brands.toggle', $brand))->assertSessionHas('success');
        $this->assertFalse($brand->fresh()->is_active);
        $this->actingAs($admin)->patch(route('admin.brands.toggle', $brand))->assertSessionHas('success');
        $this->assertTrue($brand->fresh()->is_active);
    }

    public function test_brand_in_use_cannot_be_deleted_but_unused_one_can(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $nike = $this->brand('Nike');
        $this->product('Nike 1', $nike);

        $this->actingAs($admin)->delete(route('admin.brands.destroy', $nike))->assertSessionHas('error');
        $this->assertModelExists($nike);

        $zara = $this->brand('Zara');
        $this->actingAs($admin)->delete(route('admin.brands.destroy', $zara))->assertSessionHas('success');
        $this->assertModelMissing($zara);
    }
}

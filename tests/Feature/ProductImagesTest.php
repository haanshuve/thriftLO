<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImagesTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seller = $this->seller('Batam Vintage Hub');
    }

    private function seller(string $shop): User
    {
        return User::factory()->create(['role' => 'penjual', 'seller_status' => 'verified', 'nama_toko' => $shop, 'lokasi_lapak' => 'Batam Center']);
    }

    /** @return list<UploadedFile> */
    private function photos(int $count, string $ext = 'jpg'): array
    {
        return array_map(fn ($i) => UploadedFile::fake()->image("foto{$i}.{$ext}"), range(1, $count));
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'idempotency_key' => $this->formKey(),
            'nama_barang' => 'Jaket Denim Levis',
            'mode_jual' => 'ecer',
            'kategori' => 'Fashion',
            'harga' => 275000,
            'grade' => config('thriftlo.grades')[0],
            'images' => $this->photos(3),
            'video_proof' => UploadedFile::fake()->create('jaket.mp4', 1024, 'video/mp4'),
        ], $overrides);
    }

    private function legacyProduct(User $owner): Product
    {
        Storage::disk('public')->put('products/lama.jpg', 'foto-lama');

        return Product::create([
            'user_id' => $owner->id,
            'title' => 'Kemeja Flanel Lama',
            'price' => 120000,
            'image_url' => 'products/lama.jpg',
            'status' => 'Available',
        ]);
    }

    // --- Posting barang ---

    public function test_seller_can_upload_multiple_photos_and_first_becomes_main_photo(): void
    {
        $this->actingAs($this->seller)
            ->post(route('product.store'), $this->payload([
                'images' => [
                    UploadedFile::fake()->image('depan.jpg'),
                    UploadedFile::fake()->image('belakang.png'),
                    UploadedFile::fake()->image('label.webp'),
                ],
            ]))
            ->assertSessionHasNoErrors();

        $product = Product::with('images')->sole();
        $this->assertCount(3, $product->images);
        $this->assertSame([0, 1, 2], $product->images->pluck('urutan')->all());
        // Foto pertama tetap disalin ke image_url supaya katalog tidak berubah
        $this->assertSame($product->images[0]->path, $product->image_url);
        $this->assertStringEndsWith('.jpg', $product->images[0]->path);
        $this->assertStringEndsWith('.png', $product->images[1]->path);
        $this->assertStringEndsWith('.webp', $product->images[2]->path);
        foreach ($product->images as $image) {
            Storage::disk('public')->assertExists($image->path);
        }
        // Video Proof tetap tersimpan seperti sebelumnya
        Storage::disk('public')->assertExists($product->video_proof_path);
    }

    public function test_five_photos_are_accepted(): void
    {
        $this->actingAs($this->seller)
            ->post(route('product.store'), $this->payload(['images' => $this->photos(5)]))
            ->assertSessionHasNoErrors();

        $this->assertSame(5, Product::sole()->images()->count());
    }

    public function test_more_than_five_photos_are_rejected(): void
    {
        $this->actingAs($this->seller)->from(route('dashboard'))
            ->post(route('product.store'), $this->payload(['images' => $this->photos(6)]))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHasErrorsIn('product', ['images' => 'Maksimal 5 foto per produk.']);

        $this->assertSame(0, Product::count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_listing_without_photo_is_rejected(): void
    {
        $this->actingAs($this->seller)
            ->post(route('product.store'), $this->payload(['images' => null]))
            ->assertSessionHasErrorsIn('product', ['images']);

        $this->assertSame(0, Product::count());
    }

    public function test_non_image_file_is_rejected(): void
    {
        $this->actingAs($this->seller)
            ->post(route('product.store'), $this->payload([
                'images' => [
                    UploadedFile::fake()->image('depan.jpg'),
                    UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf'),
                ],
            ]))
            ->assertSessionHasErrorsIn('product', ['images.1']);

        $this->assertSame(0, Product::count());
    }

    public function test_unsupported_image_format_is_rejected(): void
    {
        $this->actingAs($this->seller)
            ->post(route('product.store'), $this->payload(['images' => [UploadedFile::fake()->image('animasi.gif')]]))
            ->assertSessionHasErrorsIn('product', ['images.0']);

        $this->assertSame(0, Product::count());
    }

    public function test_photo_larger_than_two_megabytes_is_rejected(): void
    {
        $this->actingAs($this->seller)
            ->post(route('product.store'), $this->payload(['images' => [UploadedFile::fake()->image('besar.jpg')->size(2049)]]))
            ->assertSessionHasErrorsIn('product', ['images.0' => 'Ukuran tiap foto produk maksimal 2MB.']);

        $this->assertSame(0, Product::count());
    }

    // --- Edit barang ---

    public function test_seller_can_replace_gallery_and_old_files_are_removed(): void
    {
        $this->actingAs($this->seller)->post(route('product.store'), $this->payload());
        $product = Product::with('images')->sole();
        $oldPaths = $product->images->pluck('path')->all();

        $this->actingAs($this->seller)
            ->put(route('product.update', $product->id), $this->payload(['images' => $this->photos(2, 'png'), 'video_proof' => null]))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHasNoErrors();

        $product->refresh()->load('images');
        $this->assertCount(2, $product->images);
        $this->assertSame($product->images[0]->path, $product->image_url);
        foreach ($oldPaths as $path) {
            Storage::disk('public')->assertMissing($path);
        }
        foreach ($product->images as $image) {
            Storage::disk('public')->assertExists($image->path);
        }
        // Video lama tidak ikut terhapus saat hanya foto yang diganti
        Storage::disk('public')->assertExists($product->video_proof_path);
    }

    public function test_editing_without_new_photos_keeps_gallery(): void
    {
        $this->actingAs($this->seller)->post(route('product.store'), $this->payload());
        $product = Product::sole();
        $before = $product->images()->pluck('path')->all();

        $this->actingAs($this->seller)
            ->put(route('product.update', $product->id), $this->payload(['nama_barang' => 'Judul Baru', 'images' => null, 'video_proof' => null]))
            ->assertSessionHasNoErrors();

        $this->assertSame('Judul Baru', $product->refresh()->title);
        $this->assertSame($before, $product->images()->pluck('path')->all());
    }

    public function test_editing_with_more_than_five_photos_is_rejected(): void
    {
        $this->actingAs($this->seller)->post(route('product.store'), $this->payload());
        $product = Product::sole();

        $this->actingAs($this->seller)
            ->put(route('product.update', $product->id), $this->payload(['images' => $this->photos(6), 'video_proof' => null]))
            ->assertSessionHasErrorsIn('product', ['images']);

        $this->assertSame(3, $product->images()->count());
    }

    public function test_other_seller_cannot_change_photos(): void
    {
        $this->actingAs($this->seller)->post(route('product.store'), $this->payload());
        $product = Product::sole();
        $before = $product->images()->pluck('path')->all();
        $intruder = $this->seller('Lapak Lain');

        $this->actingAs($intruder)
            ->put(route('product.update', $product->id), $this->payload(['images' => $this->photos(1), 'video_proof' => null]))
            ->assertNotFound();

        $this->actingAs($intruder)->get(route('product.edit', $product->id))->assertNotFound();

        $this->assertSame($before, $product->images()->pluck('path')->all());
        $this->assertSame($before[0], $product->refresh()->image_url);
        foreach ($before as $path) {
            Storage::disk('public')->assertExists($path);
        }
    }

    // --- Produk lama (satu foto, belum punya baris product_images) ---

    public function test_legacy_single_photo_product_still_shows_its_photo(): void
    {
        $product = $this->legacyProduct($this->seller);

        $this->assertSame([asset('storage/products/lama.jpg')], $product->galleryUrls());

        $this->get(route('product.show', $product))
            ->assertOk()
            ->assertSee(asset('storage/products/lama.jpg'), false)
            ->assertDontSee('data-gallery-thumb', false);
    }

    public function test_legacy_product_can_be_edited_without_photos_and_keeps_old_photo(): void
    {
        $product = $this->legacyProduct($this->seller);

        $this->actingAs($this->seller)
            ->put(route('product.update', $product->id), $this->payload(['images' => null, 'video_proof' => null]))
            ->assertSessionHasNoErrors();

        $this->assertSame('products/lama.jpg', $product->refresh()->image_url);
        Storage::disk('public')->assertExists('products/lama.jpg');
    }

    public function test_legacy_product_gets_gallery_and_old_photo_is_removed_when_replaced(): void
    {
        $product = $this->legacyProduct($this->seller);

        $this->actingAs($this->seller)
            ->put(route('product.update', $product->id), $this->payload(['images' => $this->photos(2), 'video_proof' => null]))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, $product->images()->count());
        Storage::disk('public')->assertMissing('products/lama.jpg');
    }

    // --- Halaman detail ---

    public function test_detail_page_shows_gallery_with_clickable_thumbnails(): void
    {
        $this->actingAs($this->seller)->post(route('product.store'), $this->payload());
        $product = Product::with('images')->sole();

        $response = $this->get(route('product.show', $product))->assertOk();

        $response->assertSee('id="galleryMain"', false)
            ->assertSee('1/3')
            ->assertSee('Lihat foto 3 dari 3');
        foreach ($product->images as $image) {
            $response->assertSee('data-src="' . asset('storage/' . $image->path) . '"', false);
        }
    }

    public function test_deleting_product_removes_all_photo_files(): void
    {
        $this->actingAs($this->seller)->post(route('product.store'), $this->payload());
        $product = Product::sole();
        $paths = $product->images()->pluck('path')->all();

        $this->actingAs($this->seller)->delete(route('product.destroy', $product->id));

        $this->assertSame(0, Product::count());
        $this->assertDatabaseCount('product_images', 0);
        foreach ($paths as $path) {
            Storage::disk('public')->assertMissing($path);
        }
    }
}

<?php

namespace Tests\Feature;

use App\Http\Controllers\ProductController;
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

    /** Produk dengan 3 foto (urutan 0..2) hasil posting biasa */
    private function productWithThreePhotos(): Product
    {
        $this->actingAs($this->seller)->post(route('product.store'), $this->payload());

        return Product::with('images')->sole();
    }

    private function edit(Product $product, array $overrides = [])
    {
        return $this->actingAs($this->seller)->from(route('product.edit', $product->id))
            ->put(route('product.update', $product->id), $this->payload(['images' => null, 'video_proof' => null, ...$overrides]));
    }

    public function test_adding_photos_on_edit_keeps_existing_photos(): void
    {
        $product = $this->productWithThreePhotos();
        $oldPaths = $product->images->pluck('path')->all();

        $this->edit($product, ['images' => $this->photos(2, 'png')])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHasNoErrors();

        $product->refresh()->load('images');
        $this->assertCount(5, $product->images);
        // Foto lama tetap di depan dengan urutan semula, foto baru ditambahkan di belakang
        $this->assertSame($oldPaths, $product->images->take(3)->pluck('path')->all());
        $this->assertSame([0, 1, 2, 3, 4], $product->images->pluck('urutan')->all());
        $this->assertStringEndsWith('.png', $product->images[3]->path);
        $this->assertStringEndsWith('.png', $product->images[4]->path);
        // Foto utama tidak berubah
        $this->assertSame($oldPaths[0], $product->image_url);
        foreach ($product->images as $image) {
            Storage::disk('public')->assertExists($image->path);
        }
        // Video lama tidak ikut terhapus saat foto ditambah
        Storage::disk('public')->assertExists($product->video_proof_path);
    }

    public function test_removing_one_photo_only_deletes_the_selected_photo(): void
    {
        $product = $this->productWithThreePhotos();
        [$first, $middle, $last] = $product->images->all();

        $this->edit($product, ['hapus_foto' => [(string) $middle->id]])->assertSessionHasNoErrors();

        $product->refresh()->load('images');
        $this->assertSame([$first->path, $last->path], $product->images->pluck('path')->all());
        // Urutan dirapikan tanpa celah
        $this->assertSame([0, 1], $product->images->pluck('urutan')->all());
        $this->assertSame($first->path, $product->image_url);
        Storage::disk('public')->assertMissing($middle->path);
        Storage::disk('public')->assertExists($first->path);
        Storage::disk('public')->assertExists($last->path);
    }

    public function test_removing_main_photo_makes_next_photo_main(): void
    {
        $product = $this->productWithThreePhotos();
        [$first, $second, $third] = $product->images->all();

        $this->edit($product, ['hapus_foto' => [(string) $first->id], 'images' => $this->photos(1, 'webp')])->assertSessionHasNoErrors();

        $product->refresh()->load('images');
        $this->assertCount(3, $product->images);
        $this->assertSame([$second->path, $third->path], $product->images->take(2)->pluck('path')->all());
        $this->assertStringEndsWith('.webp', $product->images[2]->path);
        $this->assertSame([0, 1, 2], $product->images->pluck('urutan')->all());
        $this->assertSame($second->path, $product->image_url);
        $this->assertSame($second->path, $product->image_path);
        Storage::disk('public')->assertMissing($first->path);

        // Halaman detail memakai foto utama yang baru
        $this->get(route('product.show', $product))->assertSee('id="galleryMain" src="' . asset('storage/' . $second->path) . '"', false);
    }

    public function test_edit_rejects_total_above_five_photos_and_changes_nothing(): void
    {
        $product = $this->productWithThreePhotos();
        $before = $product->images->pluck('path')->all();
        $filesBefore = Storage::disk('public')->allFiles();

        // 3 foto lama + 3 foto baru = 6
        $this->edit($product, ['nama_barang' => 'Judul Baru', 'images' => $this->photos(3)])
            ->assertRedirect(route('product.edit', $product->id))
            ->assertSessionHasErrorsIn('product', [
                'images' => 'Total foto maksimal 5 per produk. Produk ini masih punya 3 foto, jadi kamu hanya bisa menambah 2 foto lagi (kamu memilih 3). Hapus foto lama dulu atau pilih lebih sedikit foto.',
            ]);

        $product->refresh();
        $this->assertSame('Jaket Denim Levis', $product->title);
        $this->assertSame($before, $product->images()->pluck('path')->all());
        // Foto baru yang ditolak tidak tertinggal di storage
        $this->assertEqualsCanonicalizing($filesBefore, Storage::disk('public')->allFiles());
    }

    public function test_removing_photos_frees_quota_for_new_ones(): void
    {
        $product = $this->productWithThreePhotos();
        $removed = $product->images[0];

        // 3 - 1 + 3 = 5, tepat di batas
        $this->edit($product, ['hapus_foto' => [(string) $removed->id], 'images' => $this->photos(3)])->assertSessionHasNoErrors();

        $this->assertSame(5, $product->images()->count());
        Storage::disk('public')->assertMissing($removed->path);
    }

    public function test_full_gallery_cannot_take_one_more_photo(): void
    {
        $this->actingAs($this->seller)->post(route('product.store'), $this->payload(['images' => $this->photos(5)]));
        $product = Product::sole();

        $this->edit($product, ['images' => $this->photos(1)])
            ->assertSessionHasErrorsIn('product', ['images']);

        $this->assertSame(5, $product->images()->count());
    }

    public function test_removing_every_photo_without_new_one_is_rejected(): void
    {
        $product = $this->productWithThreePhotos();
        $ids = $product->images->pluck('id')->map(fn ($id) => (string) $id)->all();

        $this->edit($product, ['hapus_foto' => $ids])
            ->assertSessionHasErrorsIn('product', ['images' => 'Produk harus punya minimal 1 foto. Unggah foto baru kalau ingin menghapus semua foto lama.']);

        $this->assertSame(3, $product->images()->count());
        foreach ($product->images as $image) {
            Storage::disk('public')->assertExists($image->path);
        }
    }

    public function test_cannot_remove_photo_of_another_product(): void
    {
        $product = $this->productWithThreePhotos();
        $other = $this->seller('Lapak Lain');
        $this->actingAs($other)->post(route('product.store'), $this->payload(['idempotency_key' => $this->formKey()]));
        $foreign = Product::where('user_id', $other->id)->sole()->images()->first();

        $this->edit($product, ['hapus_foto' => [(string) $foreign->id]])
            ->assertSessionHasErrorsIn('product', ['hapus_foto']);

        $this->assertDatabaseHas('product_images', ['id' => $foreign->id]);
        Storage::disk('public')->assertExists($foreign->path);
        $this->assertSame(3, $product->images()->count());
    }

    public function test_edit_form_shows_existing_photos_with_remove_checkbox_and_quota(): void
    {
        $product = $this->productWithThreePhotos();

        $response = $this->actingAs($this->seller)->get(route('product.edit', $product->id))->assertOk()
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('name="images[]" multiple', false)
            ->assertSee('3 dari 5 foto');
        foreach ($product->images as $image) {
            $response->assertSee('src="' . asset('storage/' . $image->path) . '"', false)
                ->assertSee('name="hapus_foto[]" value="' . $image->id . '"', false);
        }
    }

    public function test_upload_form_starts_with_empty_quota_and_no_remove_checkbox(): void
    {
        $this->actingAs($this->seller)->get(route('dashboard'))->assertOk()
            ->assertSee('0 dari 5 foto')
            ->assertDontSee('name="hapus_foto[]"', false);
    }

    public function test_new_listing_cannot_send_photo_removals(): void
    {
        $this->actingAs($this->seller)
            ->post(route('product.store'), $this->payload(['hapus_foto' => ['1']]))
            ->assertSessionHasErrorsIn('product', ['hapus_foto']);

        $this->assertSame(0, Product::count());
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
            ->assertDontSee('data-gallery-thumb', false)
            ->assertDontSee('data-gallery-prev', false);
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

    public function test_legacy_product_keeps_old_photo_as_main_when_photos_are_added(): void
    {
        $product = $this->legacyProduct($this->seller);

        $this->edit($product, ['images' => $this->photos(2)])->assertSessionHasNoErrors();

        $product->refresh()->load('images');
        $this->assertCount(3, $product->images);
        $this->assertSame('products/lama.jpg', $product->images[0]->path);
        $this->assertSame([0, 1, 2], $product->images->pluck('urutan')->all());
        $this->assertSame('products/lama.jpg', $product->image_url);
        Storage::disk('public')->assertExists('products/lama.jpg');
    }

    public function test_legacy_product_old_photo_can_be_removed_when_replaced(): void
    {
        $product = $this->legacyProduct($this->seller);

        $this->actingAs($this->seller)->get(route('product.edit', $product->id))->assertOk()
            ->assertSee('1 dari 5 foto')
            ->assertSee('name="hapus_foto[]" value="' . ProductController::LEGACY_IMAGE_KEY . '"', false);

        $this->edit($product, ['hapus_foto' => [ProductController::LEGACY_IMAGE_KEY], 'images' => $this->photos(2)])
            ->assertSessionHasNoErrors();

        $product->refresh()->load('images');
        $this->assertCount(2, $product->images);
        $this->assertSame($product->images[0]->path, $product->image_url);
        Storage::disk('public')->assertMissing('products/lama.jpg');
    }

    public function test_legacy_product_cannot_add_more_than_four_photos(): void
    {
        $product = $this->legacyProduct($this->seller);

        // 1 foto lama + 5 baru = 6
        $this->edit($product, ['images' => $this->photos(5)])->assertSessionHasErrorsIn('product', ['images']);

        // Gagal validasi tidak meninggalkan baris galeri dari foto lama
        $this->assertSame(0, $product->images()->count());
        $this->assertSame('products/lama.jpg', $product->refresh()->image_url);
    }

    // --- Halaman detail ---

    public function test_detail_page_shows_gallery_with_clickable_thumbnails(): void
    {
        $this->actingAs($this->seller)->post(route('product.store'), $this->payload());
        $product = Product::with('images')->sole();

        $response = $this->get(route('product.show', $product))->assertOk();

        $response->assertSee('id="galleryMain"', false)
            ->assertSee('1/3')
            ->assertSee('Lihat foto 3 dari 3')
            ->assertSee('aria-label="Foto sebelumnya"', false)
            ->assertSee('aria-label="Foto berikutnya"', false);
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

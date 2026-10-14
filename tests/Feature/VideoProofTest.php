<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VideoProofTest extends TestCase
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

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'idempotency_key' => $this->formKey(),
            'nama_barang' => 'Kamera Analog Canon AE-1',
            'mode_jual' => 'ecer',
            'kategori' => 'Fashion',
            'harga' => 850000,
            'grade' => config('thriftlo.grades')[0],
            'image' => UploadedFile::fake()->image('kamera.jpg'),
            'video_proof' => UploadedFile::fake()->create('kamera.mp4', 10 * 1024, 'video/mp4'),
        ], $overrides);
    }

    private function product(User $owner, ?string $videoPath = null, ?string $legacyLink = null): Product
    {
        return Product::create([
            'user_id' => $owner->id,
            'title' => 'Jaket Kulit Vintage',
            'price' => 300000,
            'image_url' => 'products/jaket.jpg',
            'status' => 'Available',
            'video_proof' => $legacyLink,
            'video_proof_path' => $videoPath,
        ]);
    }

    // --- Upload saat menayangkan barang ---

    public function test_seller_can_list_product_with_valid_video(): void
    {
        $this->actingAs($this->seller)->post(route('product.store'), $this->payload())
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $product = Product::sole();
        $this->assertStringStartsWith('product-videos/', $product->video_proof_path);
        Storage::disk('public')->assertExists($product->video_proof_path);
    }

    public function test_mov_and_webm_videos_are_accepted(): void
    {
        foreach (['iphone.mov' => 'video/quicktime', 'rekaman.webm' => 'video/webm'] as $name => $mime) {
            $this->actingAs($this->seller)
                ->post(route('product.store'), $this->payload(['video_proof' => UploadedFile::fake()->create($name, 1024, $mime)]))
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(2, Product::whereNotNull('video_proof_path')->count());
    }

    public function test_seller_cannot_list_product_without_video(): void
    {
        $this->actingAs($this->seller)->from('/dashboard')
            ->post(route('product.store'), $this->payload(['video_proof' => null]))
            ->assertRedirect('/dashboard')
            ->assertSessionHasErrorsIn('product', ['video_proof' => 'Video Proof wajib diunggah. Rekam video singkat yang menunjukkan kondisi asli barang.']);

        $this->assertSame(0, Product::count());
    }

    public function test_wrong_video_format_is_rejected_with_clear_message(): void
    {
        $this->actingAs($this->seller)->from('/dashboard')
            ->post(route('product.store'), $this->payload(['video_proof' => UploadedFile::fake()->create('kamera.avi', 1024, 'video/x-msvideo')]))
            ->assertRedirect('/dashboard')
            ->assertSessionHasErrorsIn('product', ['video_proof' => 'Video Proof harus berformat MP4, MOV, atau WEBM.']);

        $this->assertSame(0, Product::count());
    }

    public function test_video_larger_than_50mb_is_rejected_with_clear_message(): void
    {
        $this->actingAs($this->seller)->from('/dashboard')
            ->post(route('product.store'), $this->payload(['video_proof' => UploadedFile::fake()->create('kamera.mp4', 50 * 1024 + 1, 'video/mp4')]))
            ->assertRedirect('/dashboard')
            ->assertSessionHasErrorsIn('product', 'video_proof');

        $this->assertStringContainsString('maksimal 50MB', session('errors')->getBag('product')->first('video_proof'));
        $this->assertSame(0, Product::count());
    }

    public function test_validation_error_is_shown_in_the_reopened_form(): void
    {
        $this->actingAs($this->seller)->from('/dashboard')
            ->post(route('product.store'), $this->payload(['video_proof' => UploadedFile::fake()->create('kamera.avi', 1024, 'video/x-msvideo')]));

        $this->actingAs($this->seller)->get('/dashboard')
            ->assertOk()
            ->assertSee('Video Proof harus berformat MP4, MOV, atau WEBM.')
            ->assertSee('Rekam video singkat (maks. 60 detik) yang menunjukkan kondisi asli barang.');
    }

    public function test_upload_rejected_by_php_limit_shows_clear_message(): void
    {
        // File di atas upload_max_filesize dibuang PHP (UPLOAD_ERR_INI_SIZE) dan harus tetap memberi pesan jelas
        $tooBig = new UploadedFile(tempnam(sys_get_temp_dir(), 'vid'), 'kamera.mp4', 'video/mp4', UPLOAD_ERR_INI_SIZE, true);

        $this->actingAs($this->seller)->from('/dashboard')
            ->post(route('product.store'), $this->payload(['video_proof' => $tooBig]))
            ->assertSessionHasErrorsIn('product', ['video_proof' => 'Video Proof gagal diunggah. Pastikan ukurannya tidak lebih dari 50MB lalu coba lagi.']);
    }

    public function test_post_too_large_shows_friendly_page_instead_of_failing_silently(): void
    {
        $this->actingAs($this->seller)
            ->call('POST', route('product.store'), [], [], [], ['CONTENT_LENGTH' => 1024 * 1024 * 1024])
            ->assertStatus(413)
            ->assertSee('File yang diunggah terlalu besar')
            ->assertSee('maksimal 50MB');
    }

    // --- Edit & hapus ---

    public function test_edit_without_new_video_keeps_existing_video(): void
    {
        Storage::disk('public')->put('product-videos/lama.mp4', 'video');
        $product = $this->product($this->seller, 'product-videos/lama.mp4');

        $this->actingAs($this->seller)
            ->put(route('product.update', $product->id), $this->payload(['image' => null, 'video_proof' => null]))
            ->assertSessionHasNoErrors();

        $this->assertSame('product-videos/lama.mp4', $product->fresh()->video_proof_path);
        Storage::disk('public')->assertExists('product-videos/lama.mp4');
    }

    public function test_replacing_video_deletes_old_file(): void
    {
        Storage::disk('public')->put('product-videos/lama.mp4', 'video');
        $product = $this->product($this->seller, 'product-videos/lama.mp4');

        $this->actingAs($this->seller)
            ->put(route('product.update', $product->id), $this->payload(['image' => null]))
            ->assertSessionHasNoErrors();

        $newPath = $product->fresh()->video_proof_path;
        $this->assertNotSame('product-videos/lama.mp4', $newPath);
        Storage::disk('public')->assertExists($newPath);
        Storage::disk('public')->assertMissing('product-videos/lama.mp4');
    }

    public function test_legacy_product_without_video_can_still_be_edited_and_keeps_its_link(): void
    {
        $product = $this->product($this->seller, null, 'https://youtube.com/watch?v=lama');

        $this->actingAs($this->seller)
            ->put(route('product.update', $product->id), $this->payload(['nama_barang' => 'Judul Baru', 'image' => null, 'video_proof' => null]))
            ->assertSessionHasNoErrors();

        $product->refresh();
        $this->assertSame('Judul Baru', $product->title);
        $this->assertNull($product->video_proof_path);
        $this->assertSame('https://youtube.com/watch?v=lama', $product->video_proof);
    }

    public function test_deleting_product_deletes_its_video(): void
    {
        Storage::disk('public')->put('product-videos/hapus.mp4', 'video');
        $product = $this->product($this->seller, 'product-videos/hapus.mp4');

        $this->actingAs($this->seller)->delete(route('product.destroy', $product->id));

        $this->assertModelMissing($product);
        Storage::disk('public')->assertMissing('product-videos/hapus.mp4');
    }

    public function test_other_seller_cannot_replace_someone_elses_video(): void
    {
        Storage::disk('public')->put('product-videos/asli.mp4', 'video');
        $product = $this->product($this->seller, 'product-videos/asli.mp4');
        $intruder = $this->seller('Toko Lain');

        $this->actingAs($intruder)
            ->put(route('product.update', $product->id), $this->payload())
            ->assertNotFound();

        $this->assertSame('product-videos/asli.mp4', $product->fresh()->video_proof_path);
        Storage::disk('public')->assertExists('product-videos/asli.mp4');
        $this->assertCount(1, Storage::disk('public')->allFiles('product-videos'));
    }

    // --- Tampilan untuk pembeli ---

    public function test_buyer_sees_video_on_product_detail_page(): void
    {
        $product = $this->product($this->seller, 'product-videos/kondisi.mp4');
        $buyer = User::factory()->create(['role' => 'pembeli']);

        $this->actingAs($buyer)->get(route('product.show', $product))
            ->assertOk()
            ->assertSee('Video Proof dari penjual')
            ->assertSee('<video src="' . asset('storage/product-videos/kondisi.mp4') . '" controls preload="metadata"', false);
    }

    public function test_catalog_shows_video_proof_badge_only_for_products_with_video(): void
    {
        $this->product($this->seller, 'product-videos/kondisi.mp4');
        $html = $this->get(route('home'))->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'Ada Video Proof'));

        Product::query()->update(['video_proof_path' => null]);
        $this->get(route('home'))->assertOk()->assertDontSee('Ada Video Proof');
    }
}

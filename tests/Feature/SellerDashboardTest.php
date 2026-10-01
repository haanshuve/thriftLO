<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Message;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SellerDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function seller(string $status = 'verified'): User
    {
        return User::factory()->create([
            'role' => 'penjual',
            'seller_status' => $status,
            'nama_toko' => 'Batam Vintage Hub',
            'lokasi_lapak' => 'Batam Center',
        ]);
    }

    private function validProductPayload(array $overrides = []): array
    {
        return array_merge([
            'nama_barang' => 'Sepatu Boots Kulit',
            'mode_jual' => 'ecer',
            'kategori' => 'Sepatu',
            'harga' => 650000,
            'grade' => 'Grade A (Like New)',
            'image' => UploadedFile::fake()->image('boots.jpg')->size(3000), // foto HP 3MB
            'deskripsi' => 'Ukuran 42',
        ], $overrides);
    }

    public function test_verified_seller_sees_shop_stats_products_and_cod_bookings(): void
    {
        $seller = $this->seller();
        $buyer = User::factory()->create(['name' => 'Budi Pembeli']);
        $available = Product::create(['user_id' => $seller->id, 'title' => 'Jaket Denim', 'price' => 185000, 'kategori' => 'Fashion', 'grade' => 'Grade A (Like New)', 'status' => 'Available']);
        $booked = Product::create(['user_id' => $seller->id, 'title' => 'Kamera Analog', 'price' => 1250000, 'kategori' => 'Vintage Tech', 'status' => 'Booked']);
        Booking::create(['product_id' => $booked->id, 'user_id' => $buyer->id, 'qr_token' => 'TL-SECRET01', 'cod_location' => 'Mega Mall', 'cod_schedule' => '2026-10-05 14:00:00']);

        $this->actingAs($seller)->get('/dashboard')->assertOk()
            ->assertSee('Batam Vintage Hub')
            ->assertSee('Penjual terverifikasi')
            ->assertSee('Jaket Denim')
            ->assertSee('Rp185.000')
            ->assertSee('Pakaian')               // label kategori dari config
            ->assertSee('Budi Pembeli')
            ->assertSee('Mega Mall')
            ->assertSee('05 Oct 2026, 14:00')
            ->assertSee('id="uploadModal"', false)
            ->assertDontSee('TL-SECRET01');       // token pembeli tetap disamarkan
    }

    public function test_pending_seller_sees_locked_form_and_kyc_notice(): void
    {
        $this->actingAs($this->seller('pending'))->get('/dashboard')->assertOk()
            ->assertSee('Dokumen KYC sedang ditinjau admin')
            ->assertSee('Menunggu verifikasi')
            ->assertDontSee('id="uploadModal"', false);
    }

    public function test_rejected_seller_sees_rejection_notice(): void
    {
        $this->actingAs($this->seller('rejected'))->get('/dashboard')->assertOk()
            ->assertSee('Verifikasi penjual ditolak')
            ->assertDontSee('id="uploadModal"', false);
    }

    public function test_buyer_visiting_dashboard_is_pointed_to_transactions(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'pembeli']))->get('/dashboard')->assertOk()
            ->assertSee('Halaman ini khusus penjual')
            ->assertSee(route('bookings.index'), false);
    }

    public function test_verified_seller_can_list_product_with_large_photo_and_new_category(): void
    {
        Storage::fake('public');
        $seller = $this->seller();

        $this->actingAs($seller)->from('/dashboard')->post('/seller/product/store', $this->validProductPayload())
            ->assertRedirect('/dashboard')
            ->assertSessionHas('success');

        $product = Product::where('user_id', $seller->id)->firstOrFail();
        $this->assertSame('Sepatu Boots Kulit', $product->title);
        $this->assertSame('Sepatu', $product->kategori);
        $this->assertSame('Available', $product->status);
        Storage::disk('public')->assertExists($product->image_path);
    }

    public function test_unverified_seller_cannot_list_product_even_with_direct_request(): void
    {
        Storage::fake('public');
        $seller = $this->seller('pending');

        $this->actingAs($seller)->post('/seller/product/store', $this->validProductPayload())
            ->assertRedirect('/dashboard')
            ->assertSessionHas('error');

        $this->assertSame(0, Product::count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_invalid_product_reopens_form_with_errors_and_old_input(): void
    {
        Storage::fake('public');
        $seller = $this->seller();

        $this->actingAs($seller)->from('/dashboard')
            ->post('/seller/product/store', $this->validProductPayload([
                'kategori' => 'Kategori Ngawur',
                'image' => UploadedFile::fake()->image('besar.jpg')->size(6000),
            ]))
            ->assertRedirect('/dashboard')
            ->assertSessionHasErrorsIn('product', ['kategori', 'image']);

        $this->assertSame(0, Product::count());

        $this->actingAs($seller)->get('/dashboard')->assertOk()
            ->assertSee('Barang belum tersimpan')
            ->assertSee('Ukuran foto produk maksimal 5MB.')
            ->assertSee('value="Sepatu Boots Kulit"', false);
    }

    public function test_homepage_uses_market_layout_with_categories_and_unread_badge(): void
    {
        $buyer = User::factory()->create(['role' => 'pembeli']);
        $seller = $this->seller();
        Message::create(['sender_id' => $seller->id, 'receiver_id' => $buyer->id, 'message' => 'Halo']);

        $this->actingAs($buyer)->get('/')->assertOk()
            ->assertSee('🛍️ Semua')
            ->assertSee('Elektronik')
            ->assertSee('Chat, 1 pesan belum dibaca', false)
            ->assertSee('Navigasi bawah', false);
    }

    public function test_dashboard_has_no_category_chips_but_keeps_navigation(): void
    {
        $this->actingAs($this->seller())->get('/dashboard')->assertOk()
            ->assertDontSee('🛍️ Semua')          // baris chip kategori hanya di homepage
            ->assertSee('Navigasi bawah', false)
            ->assertSee('Toko Saya');
    }
}

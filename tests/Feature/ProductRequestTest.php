<?php

namespace Tests\Feature;

use App\Models\ProductRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;
    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buyer = User::factory()->create(['name' => 'Budi', 'role' => 'pembeli']);
        $this->seller = User::factory()->create(['role' => 'penjual', 'seller_status' => 'verified']);
    }

    private function productRequest(?User $owner = null, string $name = 'Kamera Sony A6000', string $status = 'Open'): ProductRequest
    {
        return ProductRequest::create([
            'user_id' => ($owner ?? $this->buyer)->id,
            'nama_barang' => $name,
            'kategori' => 'Vintage Tech',
            'budget_maksimal' => 2500000,
            'lokasi_cod' => 'Batam Center',
            'deskripsi' => 'Shutter count di bawah 20rb',
            'status' => $status,
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'nama_barang' => 'Sepatu Dr. Martens',
            'kategori' => 'Sepatu',
            'budget_maksimal' => 700000,
            'lokasi_cod' => 'Nagoya Hill',
            'deskripsi' => 'Ukuran 42, warna hitam',
        ], $overrides);
    }

    public function test_seller_sees_open_requests_with_offer_button_to_chat_buyer(): void
    {
        $this->productRequest();
        $this->productRequest(name: 'Barang Yang Sudah Didapat', status: 'Fulfilled');

        $this->actingAs($this->seller)->get('/requests')->assertOk()
            ->assertSee('Kamera Sony A6000')
            ->assertSee('📷 Elektronik')
            ->assertSee('Rp2.500.000')
            ->assertSee('Tawarkan barang')
            ->assertSee(route('chat.index', ['user_id' => $this->buyer->id]), false)
            ->assertDontSee('Barang Yang Sudah Didapat');
    }

    public function test_my_requests_tab_shows_own_requests_including_fulfilled(): void
    {
        $this->productRequest();
        $this->productRequest(name: 'Request Lama', status: 'Fulfilled');
        $this->productRequest($this->seller, 'Request Orang Lain');

        $this->actingAs($this->buyer)->get('/requests?tab=saya')->assertOk()
            ->assertSee('Kamera Sony A6000')
            ->assertSee('Request Lama')
            ->assertSee('Sudah didapat')
            ->assertDontSee('Request Orang Lain');
    }

    public function test_buyer_can_post_request_with_new_category(): void
    {
        $this->actingAs($this->buyer)->post('/requests', $this->payload())
            ->assertRedirect(route('requests.index', ['tab' => 'saya']))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('product_requests', ['user_id' => $this->buyer->id, 'nama_barang' => 'Sepatu Dr. Martens', 'kategori' => 'Sepatu', 'status' => 'Open']);
    }

    public function test_invalid_request_reopens_form_with_errors_and_old_input(): void
    {
        $this->actingAs($this->buyer)->from('/requests')
            ->post('/requests', $this->payload(['kategori' => 'Ngawur', 'budget_maksimal' => -5]))
            ->assertRedirect('/requests')
            ->assertSessionHasErrorsIn('request', ['kategori', 'budget_maksimal']);

        $this->assertSame(0, ProductRequest::count());

        $this->actingAs($this->buyer)->get('/requests')->assertOk()
            ->assertSee('Request belum terkirim')
            ->assertSee('Budget tidak boleh negatif.')
            ->assertSee('value="Sepatu Dr. Martens"', false);
    }

    public function test_owner_can_mark_request_fulfilled(): void
    {
        $req = $this->productRequest();

        $this->actingAs($this->buyer)->patch(route('requests.fulfill', $req))
            ->assertRedirect(route('requests.index', ['tab' => 'saya']));

        $this->assertSame('Fulfilled', $req->fresh()->status);
    }

    public function test_others_cannot_mark_request_fulfilled(): void
    {
        $req = $this->productRequest();

        $this->actingAs($this->seller)->patch(route('requests.fulfill', $req))->assertForbidden();

        $this->assertSame('Open', $req->fresh()->status);
    }

    public function test_owner_sees_fulfill_button_instead_of_chat_on_own_request(): void
    {
        $this->productRequest();

        $this->actingAs($this->buyer)->get('/requests')->assertOk()
            ->assertSee('Sudah dapat')
            ->assertSee('Dicari oleh')
            ->assertDontSee('Tawarkan barang');
    }
}

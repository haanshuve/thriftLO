<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSellersTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function seller(string $status, string $shop): User
    {
        return User::factory()->create(['role' => 'penjual', 'seller_status' => $status, 'nama_toko' => $shop, 'lokasi_lapak' => 'Batam Center']);
    }

    public function test_pending_tab_is_default_and_shows_counts(): void
    {
        $this->seller('pending', 'Toko Menunggu');
        $this->seller('verified', 'Toko Sudah Oke');
        $this->seller('rejected', 'Toko Ditolak');

        $this->actingAs($this->admin)->get('/admin/sellers')->assertOk()
            ->assertSee('Toko Menunggu')
            ->assertDontSee('Toko Sudah Oke')
            ->assertDontSee('Toko Ditolak')
            ->assertSee('✓ Setujui')
            ->assertSee('Tolak verifikasi')
            ->assertSee('Navigasi bawah', false);
    }

    public function test_pending_queue_lists_longest_waiting_first(): void
    {
        $this->seller('pending', 'Toko Lama Menunggu')->forceFill(['created_at' => now()->subDays(3)])->save();
        $this->seller('pending', 'Toko Baru Daftar');

        $this->actingAs($this->admin)->get('/admin/sellers')->assertOk()
            ->assertSeeInOrder(['Toko Lama Menunggu', 'Toko Baru Daftar']);
    }

    public function test_verified_tab_offers_revoke_instead_of_approve(): void
    {
        $this->seller('verified', 'Toko Sudah Oke');

        $this->actingAs($this->admin)->get('/admin/sellers?status=verified')->assertOk()
            ->assertSee('Toko Sudah Oke')
            ->assertSee('Cabut verifikasi')
            ->assertDontSee('✓ Setujui');
    }

    public function test_rejected_tab_offers_approve_only(): void
    {
        $this->seller('rejected', 'Toko Ditolak');

        $this->actingAs($this->admin)->get('/admin/sellers?status=rejected')->assertOk()
            ->assertSee('Toko Ditolak')
            ->assertSee('✓ Setujui')
            ->assertDontSee('Cabut verifikasi');
    }

    public function test_admin_can_verify_and_reject_seller(): void
    {
        $seller = $this->seller('pending', 'Toko Menunggu');

        $this->actingAs($this->admin)->from('/admin/sellers')->post(route('admin.verifySeller', $seller->id))
            ->assertRedirect('/admin/sellers')
            ->assertSessionHas('success');
        $this->assertSame('verified', $seller->fresh()->seller_status);

        $this->actingAs($this->admin)->from('/admin/sellers?status=verified')->post(route('admin.rejectSeller', $seller->id))
            ->assertRedirect('/admin/sellers?status=verified');
        $this->assertSame('rejected', $seller->fresh()->seller_status);
    }

    public function test_verification_actions_only_apply_to_sellers(): void
    {
        $buyer = User::factory()->create(['role' => 'pembeli', 'seller_status' => 'pending']);

        $this->actingAs($this->admin)->post(route('admin.verifySeller', $buyer->id))->assertNotFound();
        $this->actingAs($this->admin)->post(route('admin.rejectSeller', $this->admin->id))->assertNotFound();

        $this->assertSame('pending', $buyer->fresh()->seller_status);
    }

    public function test_unknown_status_falls_back_to_pending(): void
    {
        $this->seller('pending', 'Toko Menunggu');

        $this->actingAs($this->admin)->get('/admin/sellers?status=ngawur')->assertOk()
            ->assertSee('Toko Menunggu');
    }
}

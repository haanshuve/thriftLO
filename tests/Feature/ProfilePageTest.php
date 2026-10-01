<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfilePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_has_logout_and_menu_for_mobile_users(): void
    {
        $buyer = User::factory()->create(['role' => 'pembeli']);

        $this->actingAs($buyer)->get('/profile')->assertOk()
            ->assertSee('Navigasi bawah', false)                     // memakai layout marketplace
            ->assertSee('action="' . route('logout') . '"', false)  // tombol Keluar tersedia di halaman
            ->assertSee('Tiket Saya')
            ->assertSee(route('requests.index'), false)
            ->assertSee('Informasi akun')
            ->assertDontSee('Nama toko');
    }

    public function test_seller_can_update_whatsapp_and_shop_details(): void
    {
        $seller = User::factory()->create(['role' => 'penjual', 'seller_status' => 'verified', 'nama_toko' => 'Toko Lama', 'lokasi_lapak' => 'Nagoya']);

        $this->actingAs($seller)->get('/profile')->assertOk()
            ->assertSee('Toko Saya')
            ->assertSee('value="Toko Lama"', false);

        $this->actingAs($seller)->patch('/profile', [
            'name' => $seller->name,
            'email' => $seller->email,
            'phone_number' => '0812 3456 7890',
            'nama_toko' => 'Batam Vintage Hub',
            'lokasi_lapak' => 'Batam Center',
        ])->assertSessionHasNoErrors()->assertRedirect('/profile');

        $seller->refresh();
        $this->assertSame('0812 3456 7890', $seller->phone_number);
        $this->assertSame('Batam Vintage Hub', $seller->nama_toko);
        $this->assertSame('Batam Center', $seller->lokasi_lapak);
    }

    public function test_seller_shop_name_is_required(): void
    {
        $seller = User::factory()->create(['role' => 'penjual', 'nama_toko' => 'Toko Lama', 'lokasi_lapak' => 'Nagoya']);

        $this->actingAs($seller)->from('/profile')->patch('/profile', [
            'name' => $seller->name,
            'email' => $seller->email,
            'nama_toko' => '',
            'lokasi_lapak' => 'Nagoya',
        ])->assertSessionHasErrors(['nama_toko' => 'Nama toko wajib diisi.']);

        $this->assertSame('Toko Lama', $seller->fresh()->nama_toko);
    }

    public function test_buyer_cannot_set_shop_fields(): void
    {
        $buyer = User::factory()->create(['role' => 'pembeli']);

        $this->actingAs($buyer)->patch('/profile', [
            'name' => $buyer->name,
            'email' => $buyer->email,
            'nama_toko' => 'Toko Palsu',
        ])->assertSessionHasNoErrors();

        $this->assertNull($buyer->fresh()->nama_toko);
    }

    public function test_invalid_whatsapp_number_is_rejected(): void
    {
        $buyer = User::factory()->create(['role' => 'pembeli']);

        $this->actingAs($buyer)->from('/profile')->patch('/profile', [
            'name' => $buyer->name,
            'email' => $buyer->email,
            'phone_number' => 'telpon saya',
        ])->assertSessionHasErrors('phone_number');
    }

    public function test_google_linked_user_sees_badge_and_password_hint(): void
    {
        $user = User::factory()->create(['role' => 'pembeli', 'google_id' => 'google-123']);

        $this->actingAs($user)->get('/profile')->assertOk()
            ->assertSee('Terhubung dengan Google')
            ->assertSee('Lupa Sandi?');
    }

    public function test_admin_sees_admin_panel_shortcut(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/profile')->assertOk()
            ->assertSee('Panel Admin')
            ->assertSee(route('admin.sellers'), false);
    }

    public function test_wrong_password_keeps_delete_section_open_with_error(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->from('/profile')->delete('/profile', ['password' => 'salah-sandi'])
            ->assertSessionHasErrorsIn('userDeletion', 'password');

        $html = $this->actingAs($user)->get('/profile')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<details class="group"\s+open/', $html);
        $this->assertNotNull($user->fresh());
    }
}

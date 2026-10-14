<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUsersTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin', 'name' => 'Admin Utama']);
    }

    public function test_lists_all_accounts_with_role_filter_and_search(): void
    {
        User::factory()->create(['role' => 'pembeli', 'name' => 'Budi Pembeli', 'email' => 'budi@contoh.id']);
        User::factory()->create(['role' => 'penjual', 'name' => 'Sinta Penjual', 'nama_toko' => 'Lapak Sinta']);

        $this->actingAs($this->admin)->get(route('admin.users'))->assertOk()
            ->assertSee('Budi Pembeli')
            ->assertSee('Sinta Penjual')
            ->assertSee('Admin Utama');

        $this->actingAs($this->admin)->get(route('admin.users', ['role' => 'penjual']))->assertOk()
            ->assertSee('Sinta Penjual')
            ->assertDontSee('Budi Pembeli');

        $this->actingAs($this->admin)->get(route('admin.users', ['q' => 'budi@contoh']))->assertOk()
            ->assertSee('Budi Pembeli')
            ->assertDontSee('Sinta Penjual');
    }

    public function test_admin_can_deactivate_and_activate_account(): void
    {
        $buyer = User::factory()->create(['role' => 'pembeli', 'name' => 'Budi']);

        $this->actingAs($this->admin)->from(route('admin.users'))->patch(route('admin.users.deactivate', $buyer))
            ->assertRedirect(route('admin.users'))
            ->assertSessionHas('success');
        $this->assertTrue($buyer->fresh()->isDeactivated());

        $this->actingAs($this->admin)->get(route('admin.users', ['status' => 'nonaktif']))->assertOk()
            ->assertSee('Budi')
            ->assertSee('Aktifkan');

        $this->actingAs($this->admin)->patch(route('admin.users.activate', $buyer))->assertSessionHas('success');
        $this->assertFalse($buyer->fresh()->isDeactivated());
    }

    public function test_admin_cannot_deactivate_own_account(): void
    {
        $this->actingAs($this->admin)->patch(route('admin.users.deactivate', $this->admin))->assertSessionHas('error');
        $this->assertFalse($this->admin->fresh()->isDeactivated());
    }

    public function test_login_rejects_deactivated_account_with_indonesian_message(): void
    {
        $user = User::factory()->create(['email' => 'nonaktif@contoh.id']);
        $user->forceFill(['deactivated_at' => now()])->save();

        $this->post('/login', ['email' => 'nonaktif@contoh.id', 'password' => 'password'])
            ->assertSessionHasErrors(['email' => User::DEACTIVATED_MESSAGE]);

        $this->assertGuest();
    }

    public function test_active_session_of_deactivated_account_is_ended(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['deactivated_at' => now()])->save();

        $this->actingAs($user)->get('/my-bookings')
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', User::DEACTIVATED_MESSAGE);

        $this->assertGuest();
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_panel_admin_menu_in_navbar(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $html = $this->actingAs($admin)->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('Panel Admin', $html);
        $this->assertStringContainsString('href="' . route('admin.sellers') . '"', $html);
        $this->assertMatchesRegularExpression('/aria-label="Navigasi bawah".*>\s*Admin\s*</s', $html);
        $this->assertStringNotContainsString('href="' . route('bookings.index') . '"', $html);
    }

    public function test_buyer_and_seller_do_not_see_panel_admin_menu(): void
    {
        foreach (['pembeli', 'penjual'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get('/')->assertOk()
                ->assertDontSee('Panel Admin')
                ->assertDontSee(route('admin.sellers'), false);
        }
    }

    public function test_admin_shortcut_url_redirects_to_seller_verification(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/admin')
            ->assertRedirect(route('admin.sellers'));
    }

    public function test_admin_shortcut_url_is_still_protected(): void
    {
        $this->get('/admin')->assertRedirect('/login');
        $this->actingAs(User::factory()->create(['role' => 'pembeli']))->get('/admin')->assertForbidden();
    }
}

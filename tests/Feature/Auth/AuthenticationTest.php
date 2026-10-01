<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create(['role' => 'pembeli']);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        // Pembeli diarahkan ke katalog
        $this->assertAuthenticated();
        $response->assertRedirect(route('home', absolute: false));
    }

    public function test_sellers_are_redirected_to_dashboard_after_login(): void
    {
        $user = User::factory()->create(['role' => 'penjual']);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_wrong_password_shows_one_friendly_alert_and_keeps_email(): void
    {
        $user = User::factory()->create();

        $this->from('/login')->followingRedirects()->post('/login', ['email' => $user->email, 'password' => 'salah'])
            ->assertSee('Email atau kata sandi belum cocok. Coba cek lagi, ya.')
            ->assertSee('role="alert"', false)
            ->assertSee('value="' . $user->email . '"', false)
            ->assertSee('aria-invalid="true"', false);
    }

    public function test_login_page_uses_new_design_and_shows_status(): void
    {
        $this->withSession(['status' => 'Kata sandimu sudah diperbarui.'])->get('/login')->assertOk()
            ->assertSee('Selamat datang kembali di thriftLO')
            ->assertSee('Kata sandimu sudah diperbarui.')
            ->assertSee('Masuk dengan Google')
            ->assertSee('images/logo.jpeg.jpeg', false)
            ->assertDontSee(route('product.store'), false);
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}

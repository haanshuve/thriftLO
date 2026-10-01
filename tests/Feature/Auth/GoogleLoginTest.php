<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Tests\TestCase;

class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    private function fakeGoogleUser(string $id = 'google-123', string $email = 'budi@example.com', bool $verified = true, string $name = 'Budi Google'): void
    {
        $googleUser = (new GoogleUser)
            ->setRaw(['email_verified' => $verified])
            ->map(['id' => $id, 'name' => $name, 'email' => $email]);

        Socialite::shouldReceive('driver->user')->andReturn($googleUser);
    }

    public function test_redirect_sends_user_to_google(): void
    {
        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-secret',
            'services.google.redirect' => 'http://localhost/auth/google/callback',
        ]);

        $response = $this->get('/auth/google/redirect');

        $response->assertRedirect();
        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/auth', $response->headers->get('Location'));
    }

    public function test_login_page_shows_google_button(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee(route('auth.google.redirect'), false)
            ->assertSee('Masuk dengan Google');
    }

    public function test_first_google_login_creates_buyer_account(): void
    {
        $this->fakeGoogleUser();

        $this->get('/auth/google/callback')->assertRedirect('/');

        $user = User::where('email', 'budi@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('google-123', $user->google_id);
        $this->assertSame('pembeli', $user->role);
        $this->assertSame('Budi Google', $user->name);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotEmpty($user->password);
        $this->assertNull($user->ktp_photo_path);
    }

    public function test_existing_email_is_linked_without_creating_duplicate(): void
    {
        $existing = User::factory()->create([
            'email' => 'budi@example.com',
            'password' => Hash::make('rahasia123'),
            'role' => 'penjual',
            'seller_status' => 'verified',
        ]);
        $this->fakeGoogleUser();

        $this->get('/auth/google/callback')->assertRedirect('/');

        $this->assertSame(1, User::where('email', 'budi@example.com')->count());
        $this->assertAuthenticatedAs($existing);
        $existing->refresh();
        $this->assertSame('google-123', $existing->google_id);
        $this->assertSame('penjual', $existing->role);               // role lama tidak berubah
        $this->assertTrue(Hash::check('rahasia123', $existing->password)); // password lama tetap
    }

    public function test_returning_google_user_is_found_by_google_id(): void
    {
        $user = User::factory()->create(['email' => 'lama@example.com', 'google_id' => 'google-123']);
        $this->fakeGoogleUser(email: 'baru@example.com'); // email di Google sudah diganti

        $this->get('/auth/google/callback')->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, User::count());
    }

    public function test_unverified_google_email_is_rejected(): void
    {
        $existing = User::factory()->create(['email' => 'budi@example.com']);
        $this->fakeGoogleUser(verified: false);

        $this->get('/auth/google/callback')
            ->assertRedirect('/login')
            ->assertSessionHas('error');

        $this->assertGuest();
        $this->assertNull($existing->fresh()->google_id);
        $this->assertSame(1, User::count());
    }

    public function test_email_linked_to_another_google_account_is_rejected(): void
    {
        User::factory()->create(['email' => 'budi@example.com', 'google_id' => 'google-lain']);
        $this->fakeGoogleUser(id: 'google-123');

        $this->get('/auth/google/callback')
            ->assertRedirect('/login')
            ->assertSessionHas('error');

        $this->assertGuest();
    }

    public function test_google_error_or_cancel_returns_to_login(): void
    {
        Socialite::shouldReceive('driver->user')->andThrow(new \Laravel\Socialite\Two\InvalidStateException);

        $this->get('/auth/google/callback')
            ->assertRedirect('/login')
            ->assertSessionHas('error');

        $this->assertGuest();
    }

    public function test_manual_email_password_login_still_works(): void
    {
        $user = User::factory()->create(['password' => Hash::make('rahasia123')]);

        $this->post('/login', ['email' => $user->email, 'password' => 'rahasia123'])->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_manual_login_still_works_after_account_is_linked_to_google(): void
    {
        $user = User::factory()->create(['email' => 'budi@example.com', 'password' => Hash::make('rahasia123')]);
        $this->fakeGoogleUser();
        $this->get('/auth/google/callback');
        $this->post('/logout');
        $this->assertGuest();

        $this->post('/login', ['email' => 'budi@example.com', 'password' => 'rahasia123'])->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }
}

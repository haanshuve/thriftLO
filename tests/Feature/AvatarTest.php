<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Tests\TestCase;

class AvatarTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->user = User::factory()->create(['name' => 'Budi Santoso', 'role' => 'pembeli']);
    }

    private function upload(User $as, $file)
    {
        return $this->actingAs($as)->from(route('profile.edit'))->post(route('profile.avatar.update'), ['avatar' => $file]);
    }

    // --- Unggah & validasi ---

    public function test_valid_photo_is_uploaded_to_public_avatars_folder(): void
    {
        $this->upload($this->user, UploadedFile::fake()->image('budi.jpg', 400, 400))
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'avatar-updated');

        $path = $this->user->fresh()->avatar_path;
        $this->assertStringStartsWith('avatars/', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_png_and_webp_are_accepted(): void
    {
        foreach (['budi.png', 'budi.webp'] as $name) {
            $this->upload($this->user, UploadedFile::fake()->image($name))->assertSessionHasNoErrors();
        }

        $this->assertStringEndsWith('.webp', $this->user->fresh()->avatar_path);
    }

    public function test_wrong_format_is_rejected_with_indonesian_message(): void
    {
        $this->upload($this->user, UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf'))
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasErrorsIn('avatar', ['avatar' => 'Foto profil harus berupa gambar (JPG, PNG, atau WEBP).']);

        $this->upload($this->user, UploadedFile::fake()->image('animasi.gif'))
            ->assertSessionHasErrorsIn('avatar', ['avatar' => 'Foto profil harus berformat JPG, PNG, atau WEBP.']);

        $this->assertNull($this->user->fresh()->avatar_path);
    }

    public function test_file_larger_than_2mb_is_rejected(): void
    {
        $this->upload($this->user, UploadedFile::fake()->image('besar.jpg')->size(2049))
            ->assertSessionHasErrorsIn('avatar', ['avatar' => 'Ukuran foto profil maksimal 2MB.']);

        $this->assertNull($this->user->fresh()->avatar_path);
        $this->assertSame([], Storage::disk('public')->allFiles('avatars'));
    }

    public function test_validation_error_is_shown_on_profile_page(): void
    {
        $this->upload($this->user, UploadedFile::fake()->image('besar.jpg')->size(2049));

        $this->actingAs($this->user)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Foto profil')
            ->assertSee('Ukuran foto profil maksimal 2MB.');
    }

    // --- Ganti & hapus ---

    public function test_replacing_photo_deletes_old_file(): void
    {
        $this->upload($this->user, UploadedFile::fake()->image('lama.jpg'));
        $oldPath = $this->user->fresh()->avatar_path;

        $this->upload($this->user, UploadedFile::fake()->image('baru.jpg'))->assertSessionHasNoErrors();

        $newPath = $this->user->fresh()->avatar_path;
        $this->assertNotSame($oldPath, $newPath);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($newPath);
    }

    public function test_user_can_delete_photo(): void
    {
        $this->upload($this->user, UploadedFile::fake()->image('budi.jpg'));
        $path = $this->user->fresh()->avatar_path;

        $this->actingAs($this->user)->delete(route('profile.avatar.destroy'))
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('status', 'avatar-deleted');

        $this->assertNull($this->user->fresh()->avatar_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_deleting_account_deletes_photo(): void
    {
        $this->upload($this->user, UploadedFile::fake()->image('budi.jpg'));
        $path = $this->user->fresh()->avatar_path;

        $this->actingAs($this->user)->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertRedirect('/');

        $this->assertModelMissing($this->user);
        Storage::disk('public')->assertMissing($path);
    }

    // --- Otorisasi ---

    public function test_other_user_cannot_change_someone_elses_photo(): void
    {
        $this->upload($this->user, UploadedFile::fake()->image('budi.jpg'));
        $budiPath = $this->user->fresh()->avatar_path;
        $other = User::factory()->create(['name' => 'Penyusup']);

        // Menyelipkan id user lain tidak berpengaruh: route selalu memakai akun yang sedang login
        $this->actingAs($other)->post(route('profile.avatar.update'), ['avatar' => UploadedFile::fake()->image('x.jpg'), 'user_id' => $this->user->id]);
        $this->actingAs($other)->delete(route('profile.avatar.destroy'), ['user_id' => $this->user->id]);

        $this->assertSame($budiPath, $this->user->fresh()->avatar_path);
        Storage::disk('public')->assertExists($budiPath);
    }

    public function test_guest_cannot_upload_or_delete_photo(): void
    {
        $this->post(route('profile.avatar.update'), ['avatar' => UploadedFile::fake()->image('x.jpg')])->assertRedirect(route('login'));
        $this->delete(route('profile.avatar.destroy'))->assertRedirect(route('login'));

        $this->assertSame([], Storage::disk('public')->allFiles('avatars'));
    }

    // --- Komponen <x-avatar> ---

    public function test_component_shows_initial_without_photo(): void
    {
        $html = Blade::render('<x-avatar :user="$user" size="md" />', ['user' => $this->user]);

        $this->assertStringContainsString('<span>B</span>', $html);
        $this->assertStringNotContainsString('<img', $html);
    }

    public function test_component_uses_custom_name_for_initial(): void
    {
        $html = Blade::render('<x-avatar :user="$user" :name="$toko" />', ['user' => $this->user, 'toko' => 'pojok vintage']);

        $this->assertStringContainsString('<span>P</span>', $html);
    }

    public function test_component_shows_uploaded_photo(): void
    {
        $this->user->update(['avatar_path' => 'avatars/budi.jpg']);

        $html = Blade::render('<x-avatar :user="$user" />', ['user' => $this->user]);

        $this->assertStringContainsString('src="' . asset('storage/avatars/budi.jpg') . '"', $html);
    }

    public function test_photo_appears_in_navbar_and_seller_card(): void
    {
        $seller = User::factory()->create(['role' => 'penjual', 'seller_status' => 'verified', 'nama_toko' => 'Pojok Vintage', 'lokasi_lapak' => 'Batam Center', 'avatar_path' => 'avatars/toko.jpg']);
        $product = Product::create(['user_id' => $seller->id, 'title' => 'Jaket', 'price' => 100000, 'image_url' => 'products/j.jpg', 'status' => 'Available']);

        $this->actingAs($seller)->get(route('home'))->assertSee(asset('storage/avatars/toko.jpg'), false);
        $this->actingAs($this->user)->get(route('product.show', $product))->assertSee(asset('storage/avatars/toko.jpg'), false);
    }

    // --- Foto Google ---

    public function test_google_photo_is_default_until_own_photo_is_uploaded(): void
    {
        $googleUser = (new GoogleUser)
            ->setRaw(['email_verified' => true])
            ->map(['id' => 'google-1', 'name' => 'Budi Google', 'email' => 'budi.google@example.com', 'avatar' => 'https://lh3.googleusercontent.com/a/budi']);
        Socialite::shouldReceive('driver->user')->andReturn($googleUser);

        $this->get('/auth/google/callback');
        $user = User::where('email', 'budi.google@example.com')->firstOrFail();
        $this->assertSame('https://lh3.googleusercontent.com/a/budi', $user->avatarUrl());

        $this->upload($user, UploadedFile::fake()->image('sendiri.jpg'));
        $this->assertSame(asset('storage/' . $user->fresh()->avatar_path), $user->fresh()->avatarUrl());
    }
}

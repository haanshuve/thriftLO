<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\SellerLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SellerLocationTest extends TestCase
{
    use RefreshDatabase;

    public static function legacyLocations(): array
    {
        return [
            'kawasan huruf kecil'      => ['botania', 'Botania'],
            'nama resmi'               => ['Batam Center', 'Batam Center'],
            'ejaan centre'             => ['BATAM CENTRE', 'Batam Center'],
            'kawasan di dalam kalimat' => ['Kota batam, Nagoya', 'Nagoya'],
            'singkatan tj'             => ['Tj. Piayu', 'Tanjung Piayu'],
            'sungai beduk'             => ['Sungai Beduk', 'Sei Beduk'],
            'batu aji digabung'        => ['batuaji', 'Batu Aji'],
            'hanya kata batam'         => ['Batam', 'Batam lainnya'],
            'kota lain'                => ['Jakarta Selatan', 'Luar Batam'],
            'kosong'                   => ['', 'Luar Batam'],
            'null'                     => [null, 'Luar Batam'],
            'sudah luar batam'         => ['Luar Batam', 'Luar Batam'],
            'teks luar batam'          => ['luar batam (Tanjung Pinang)', 'Luar Batam'],
            'sudah batam lainnya'      => ['Batam lainnya', 'Batam lainnya'],
        ];
    }

    #[DataProvider('legacyLocations')]
    public function test_legacy_free_text_is_matched_to_closest_option(?string $text, string $expected): void
    {
        $this->assertSame($expected, SellerLocation::match($text));
    }

    public function test_label_adds_batam_for_areas_without_it(): void
    {
        $this->assertSame('Nagoya, Batam', SellerLocation::label('Nagoya'));
        $this->assertSame('Batam Center', SellerLocation::label('Batam Center'));
        $this->assertSame('Batam', SellerLocation::label('Batam lainnya'));
        $this->assertSame('Luar Batam', SellerLocation::label('Luar Batam'));
    }

    public function test_migration_normalizes_existing_seller_locations(): void
    {
        $botania = User::factory()->create(['role' => 'penjual', 'lokasi_lapak' => 'botania']);
        $surabaya = User::factory()->create(['role' => 'penjual', 'lokasi_lapak' => 'Surabaya']);
        $empty = User::factory()->create(['role' => 'penjual', 'lokasi_lapak' => null]);
        $buyer = User::factory()->create(['role' => 'pembeli', 'lokasi_lapak' => null]);

        (require database_path('migrations/2026_10_02_000004_normalize_seller_locations.php'))->up();

        $this->assertSame('Botania', $botania->fresh()->lokasi_lapak);
        $this->assertTrue($botania->fresh()->isInBatam());
        $this->assertSame('Luar Batam', $surabaya->fresh()->lokasi_lapak);
        $this->assertSame('Luar Batam', $empty->fresh()->lokasi_lapak);
        $this->assertNull($buyer->fresh()->lokasi_lapak);
    }

    private function registerSeller(string $location)
    {
        Storage::fake('local');

        return $this->post('/register', [
            'name' => 'Rina',
            'phone_number' => '081234567890',
            'email' => 'rina@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'penjual',
            'nama_toko' => 'Rina Thrift',
            'lokasi_lapak' => $location,
            'ktp_photo' => UploadedFile::fake()->image('ktp.jpg'),
            'selfie_ktp' => UploadedFile::fake()->image('selfie.jpg'),
        ]);
    }

    public function test_registration_shows_location_dropdown(): void
    {
        $this->get('/register')->assertOk()
            ->assertSee('<select id="lokasi_lapak" name="lokasi_lapak"', false)
            ->assertSee('<option value="Botania"', false)
            ->assertSee('Luar Batam (hanya pengiriman)');
    }

    public function test_seller_registers_with_location_from_dropdown(): void
    {
        $this->registerSeller('Sekupang')->assertSessionHasNoErrors();

        $seller = User::where('email', 'rina@example.com')->first();
        $this->assertSame('Sekupang', $seller->lokasi_lapak);
        $this->assertTrue($seller->isInBatam());
    }

    public function test_registration_rejects_location_outside_the_list(): void
    {
        $this->registerSeller('Jakarta Selatan')->assertSessionHasErrors('lokasi_lapak');

        $this->assertDatabaseMissing('users', ['email' => 'rina@example.com']);
    }

    public function test_luar_batam_registration_requires_city(): void
    {
        $this->registerSeller('Luar Batam')->assertSessionHasErrors('kota_lapak');
        $this->assertDatabaseMissing('users', ['email' => 'rina@example.com']);

        Storage::fake('local');
        $this->post('/register', [
            'name' => 'Rina', 'phone_number' => '081234567890', 'email' => 'rina@example.com',
            'password' => 'password', 'password_confirmation' => 'password', 'role' => 'penjual',
            'nama_toko' => 'Rina Thrift', 'lokasi_lapak' => 'Luar Batam', 'kota_lapak' => ' Surabaya ',
            'ktp_photo' => UploadedFile::fake()->image('ktp.jpg'), 'selfie_ktp' => UploadedFile::fake()->image('selfie.jpg'),
        ])->assertSessionHasNoErrors();

        $seller = User::where('email', 'rina@example.com')->first();
        $this->assertSame('Surabaya', $seller->kota_lapak);
        $this->assertSame('Surabaya', $seller->locationLabel());
        $this->assertFalse($seller->isInBatam());
    }

    public function test_city_is_ignored_for_batam_sellers(): void
    {
        $this->registerSeller('Nagoya');
        $this->assertNull(User::where('email', 'rina@example.com')->first()->kota_lapak);

        $seller = User::factory()->create(['role' => 'penjual', 'nama_toko' => 'Toko', 'lokasi_lapak' => 'Luar Batam', 'kota_lapak' => 'Medan']);
        $this->actingAs($seller)->patch('/profile', [
            'name' => $seller->name, 'email' => $seller->email, 'nama_toko' => 'Toko', 'lokasi_lapak' => 'Sekupang', 'kota_lapak' => 'Medan',
        ])->assertSessionHasNoErrors();

        $this->assertNull($seller->fresh()->kota_lapak);
        $this->assertSame('Sekupang, Batam', $seller->fresh()->locationLabel());
    }

    public function test_city_migration_keeps_original_text_before_location_is_normalized(): void
    {
        $surabaya = User::factory()->create(['role' => 'penjual', 'lokasi_lapak' => 'Surabaya']);
        $botania = User::factory()->create(['role' => 'penjual', 'lokasi_lapak' => 'botania']);

        // Urutan seperti di server yang belum menjalankan kedua migrasi
        $cityMigration = require database_path('migrations/2026_10_02_000003_add_kota_lapak_to_users_table.php');
        $cityMigration->down();
        $cityMigration->up();
        (require database_path('migrations/2026_10_02_000004_normalize_seller_locations.php'))->up();

        $this->assertSame('Luar Batam', $surabaya->fresh()->lokasi_lapak);
        $this->assertSame('Surabaya', $surabaya->fresh()->kota_lapak);
        $this->assertSame('Botania', $botania->fresh()->lokasi_lapak);
        $this->assertNull($botania->fresh()->kota_lapak);
    }

    public function test_seller_changes_location_in_profile_and_cod_follows(): void
    {
        $seller = User::factory()->create(['role' => 'penjual', 'seller_status' => 'verified', 'nama_toko' => 'Rina Thrift', 'lokasi_lapak' => 'Nagoya']);

        $this->actingAs($seller)->get('/profile')->assertOk()
            ->assertSee('<option value="Nagoya" selected', false);

        $this->actingAs($seller)->patch('/profile', [
            'name' => $seller->name, 'email' => $seller->email, 'nama_toko' => 'Rina Thrift', 'lokasi_lapak' => 'Luar Batam', 'kota_lapak' => 'Pekanbaru',
        ])->assertSessionHasNoErrors();
        $this->assertFalse($seller->fresh()->isInBatam());
        $this->assertSame('Pekanbaru', $seller->fresh()->locationLabel());

        $this->actingAs($seller)->patch('/profile', [
            'name' => $seller->name, 'email' => $seller->email, 'nama_toko' => 'Rina Thrift', 'lokasi_lapak' => 'Medan',
        ])->assertSessionHasErrors('lokasi_lapak');
        $this->assertSame('Luar Batam', $seller->fresh()->lokasi_lapak);
    }
}

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

    public function test_seller_changes_location_in_profile_and_cod_follows(): void
    {
        $seller = User::factory()->create(['role' => 'penjual', 'seller_status' => 'verified', 'nama_toko' => 'Rina Thrift', 'lokasi_lapak' => 'Nagoya']);

        $this->actingAs($seller)->get('/profile')->assertOk()
            ->assertSee('<option value="Nagoya" selected', false);

        $this->actingAs($seller)->patch('/profile', [
            'name' => $seller->name, 'email' => $seller->email, 'nama_toko' => 'Rina Thrift', 'lokasi_lapak' => 'Luar Batam',
        ])->assertSessionHasNoErrors();
        $this->assertFalse($seller->fresh()->isInBatam());

        $this->actingAs($seller)->patch('/profile', [
            'name' => $seller->name, 'email' => $seller->email, 'nama_toko' => 'Rina Thrift', 'lokasi_lapak' => 'Medan',
        ])->assertSessionHasErrors('lokasi_lapak');
        $this->assertSame('Luar Batam', $seller->fresh()->lokasi_lapak);
    }
}

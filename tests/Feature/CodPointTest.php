<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CodPointTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seller = User::factory()->create([
            'role' => 'penjual', 'seller_status' => 'verified', 'nama_toko' => 'Nagoya Thrift', 'lokasi_lapak' => 'Nagoya',
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'cod_address' => 'Depan Nagoya Hill Mall, pintu utama',
            'cod_latitude' => '1.1442',
            'cod_longitude' => '104.0109',
        ], $overrides);
    }

    private function save(array $overrides = [], ?User $user = null)
    {
        return $this->actingAs($user ?? $this->seller)->from(route('profile.edit'))
            ->patch(route('profile.cod-point.update'), $this->payload($overrides));
    }

    private function sellerWithPoint(): User
    {
        $this->seller->update(['cod_address' => 'Depan Nagoya Hill Mall', 'cod_latitude' => 1.1442, 'cod_longitude' => 104.0109]);

        return $this->seller->refresh();
    }

    // --- Simpan titik COD ---

    public function test_seller_can_save_cod_point(): void
    {
        $this->save()->assertRedirect(route('profile.edit') . '#titik-cod')->assertSessionHasNoErrors();

        $this->seller->refresh();
        $this->assertSame('Depan Nagoya Hill Mall, pintu utama', $this->seller->cod_address);
        $this->assertSame(1.1442, $this->seller->cod_latitude);
        $this->assertSame(104.0109, $this->seller->cod_longitude);
    }

    // --- Wilayah Batam ---

    public static function pointsInBatam(): array
    {
        return [
            'Batam Centre'               => [1.1301, 104.0529],
            'Nagoya'                     => [1.1442, 104.0109],
            'Batu Aji'                   => [1.0437, 103.9806],
            'Nongsa Point'               => [1.1990, 104.0980],
            'Belakang Padang'            => [1.1570, 103.8930],
            'Jembatan Barelang 1'        => [1.0640, 104.0180],
            'Kamp Vietnam, Galang'       => [0.7556, 104.2456],
            'sudut kotak Batam (min)'    => [0.95, 103.85],
            'sudut kotak Batam (maks)'   => [1.215, 104.20],
        ];
    }

    #[DataProvider('pointsInBatam')]
    public function test_point_in_batam_is_accepted(float $lat, float $lng): void
    {
        $this->save(['cod_latitude' => (string) $lat, 'cod_longitude' => (string) $lng])->assertSessionHasNoErrors();

        $this->assertSame($lat, $this->seller->refresh()->cod_latitude);
        $this->assertSame($lng, $this->seller->cod_longitude);
    }

    public static function pointsOutsideBatam(): array
    {
        return [
            'Jakarta (Monas)'            => [-6.1754, 106.8272],
            'Marina Bay, Singapura'      => [1.2834, 103.8607],
            'Tanjung Uban, Bintan'       => [1.0667, 104.2167],
            'Tanjung Pinang'             => [0.9186, 104.4466],
            'Johor Bahru'                => [1.4927, 103.7414],
            'kutub (rentang valid)'      => [-90, 180],
            'tepat di luar utara Batam'  => [1.2151, 104.0],
        ];
    }

    #[DataProvider('pointsOutsideBatam')]
    public function test_point_outside_batam_is_rejected(float $lat, float $lng): void
    {
        $this->save(['cod_latitude' => (string) $lat, 'cod_longitude' => (string) $lng])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasErrorsIn('codPoint', ['cod_latitude' => 'Titik COD harus berada di wilayah Batam.']);

        $this->assertNull($this->seller->refresh()->cod_latitude);
    }

    public function test_batam_areas_come_from_config(): void
    {
        // Batas bisa diubah lewat config tanpa menyentuh kode
        config(['thriftlo.cod_point.areas' => [['label' => 'Jakarta', 'lat' => [-6.4, -6.0], 'lng' => [106.6, 107.0]]]]);

        $this->save(['cod_latitude' => '-6.1754', 'cod_longitude' => '106.8272'])->assertSessionHasNoErrors();
        $this->save()->assertSessionHasErrorsIn('codPoint', ['cod_latitude' => 'Titik COD harus berada di wilayah Batam.']);
    }

    public function test_out_of_range_coordinate_shows_range_error_not_batam_error(): void
    {
        $this->save(['cod_latitude' => '95'])
            ->assertSessionHasErrorsIn('codPoint', ['cod_latitude' => 'Latitude harus di antara -90 dan 90.']);

        $this->assertNotContains('Titik COD harus berada di wilayah Batam.', session('errors')->getBag('codPoint')->all());
    }

    public function test_profile_form_receives_batam_areas_for_browser_check(): void
    {
        $this->actingAs($this->seller)->get(route('profile.edit'))->assertOk()
            ->assertSee('data-areas=', false)
            ->assertSee('Pulau Batam &amp; Belakang Padang', false);
    }

    public static function outOfRangeCoordinates(): array
    {
        return [
            'latitude terlalu besar'  => ['cod_latitude', '90.0001', 'Latitude harus di antara -90 dan 90.'],
            'latitude terlalu kecil'  => ['cod_latitude', '-91', 'Latitude harus di antara -90 dan 90.'],
            'longitude terlalu besar' => ['cod_longitude', '180.5', 'Longitude harus di antara -180 dan 180.'],
            'longitude terlalu kecil' => ['cod_longitude', '-200', 'Longitude harus di antara -180 dan 180.'],
            'latitude bukan angka'    => ['cod_latitude', 'satu koma satu', 'Latitude harus berupa angka.'],
        ];
    }

    #[DataProvider('outOfRangeCoordinates')]
    public function test_out_of_range_coordinates_are_rejected(string $field, string $value, string $message): void
    {
        $this->save([$field => $value])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasErrorsIn('codPoint', [$field => $message]);

        $this->assertNull($this->seller->refresh()->cod_latitude);
    }

    public function test_missing_pin_is_rejected(): void
    {
        $this->save(['cod_latitude' => '', 'cod_longitude' => ''])
            ->assertSessionHasErrorsIn('codPoint', ['cod_latitude', 'cod_longitude']);
    }

    public function test_address_at_limit_is_accepted(): void
    {
        $address = str_repeat('a', 150);

        $this->save(['cod_address' => $address])->assertSessionHasNoErrors();

        $this->assertSame($address, $this->seller->refresh()->cod_address);
    }

    public function test_address_over_limit_is_rejected(): void
    {
        $this->save(['cod_address' => str_repeat('a', 151)])
            ->assertSessionHasErrorsIn('codPoint', ['cod_address' => 'Patokan alamat COD maksimal 150 karakter.']);

        $this->assertNull($this->seller->refresh()->cod_address);
    }

    public function test_address_limit_counts_characters_not_bytes(): void
    {
        // 150 karakter multibyte (é = 2 byte) tetap lolos, 151 ditolak
        $this->save(['cod_address' => str_repeat('é', 150)])->assertSessionHasNoErrors();
        $this->save(['cod_address' => str_repeat('é', 151)])->assertSessionHasErrorsIn('codPoint', ['cod_address']);
    }

    public function test_surrounding_spaces_are_trimmed_before_length_check(): void
    {
        $this->save(['cod_address' => '   ' . str_repeat('a', 150) . '   '])->assertSessionHasNoErrors();
        $this->assertSame(str_repeat('a', 150), $this->seller->refresh()->cod_address);

        $this->save(['cod_address' => '    '])->assertSessionHasErrorsIn('codPoint', ['cod_address']);
    }

    public function test_buyer_cannot_set_cod_point(): void
    {
        $buyer = User::factory()->create(['role' => 'pembeli']);

        $this->save([], $buyer)->assertForbidden();
        $this->assertNull($buyer->refresh()->cod_latitude);
    }

    public function test_seller_outside_batam_cannot_set_cod_point(): void
    {
        $this->seller->update(['lokasi_lapak' => 'Luar Batam', 'kota_lapak' => 'Surabaya']);

        $this->save()->assertSessionHasErrorsIn('codPoint', ['cod_address']);
        $this->assertNull($this->seller->refresh()->cod_latitude);
    }

    public function test_seller_can_remove_cod_point(): void
    {
        $this->sellerWithPoint();

        $this->actingAs($this->seller)->delete(route('profile.cod-point.destroy'))->assertRedirect();

        $this->seller->refresh();
        $this->assertNull($this->seller->cod_address);
        $this->assertNull($this->seller->cod_latitude);
        $this->assertNull($this->seller->cod_longitude);
    }

    // --- Form di profil ---

    public function test_profile_form_has_map_counter_and_matching_maxlength(): void
    {
        $this->sellerWithPoint();

        $this->actingAs($this->seller)->get(route('profile.edit'))->assertOk()
            ->assertSee('id="codMap"', false)
            ->assertSee('Gunakan lokasi saya')
            ->assertSee('maxlength="150"', false)
            ->assertSee('22/150')
            ->assertSee('leaflet@1.9.4', false)
            ->assertSee('data-center-lat="1.1301"', false);
    }

    public function test_buyer_profile_has_no_cod_point_form(): void
    {
        $buyer = User::factory()->create(['role' => 'pembeli']);

        $this->actingAs($buyer)->get(route('profile.edit'))->assertOk()->assertDontSee('id="codMap"', false);
    }

    // --- Tampilan ke pembeli ---

    public function test_product_page_shows_static_map_and_google_maps_link(): void
    {
        $this->sellerWithPoint();
        $product = Product::create(['user_id' => $this->seller->id, 'title' => 'Jaket Parasut', 'price' => 90000, 'status' => 'Available']);

        $this->get(route('product.show', $product))->assertOk()
            ->assertSee('Lokasi acuan COD penjual')
            ->assertSee('Depan Nagoya Hill Mall')
            ->assertSee('tile.openstreetmap.org/16/', false)
            ->assertSee('https://www.google.com/maps/search/?api=1&amp;query=1.1442%2C104.0109', false);
    }

    public function test_product_page_without_cod_point_has_no_map(): void
    {
        $product = Product::create(['user_id' => $this->seller->id, 'title' => 'Jaket Parasut', 'price' => 90000, 'status' => 'Available']);

        // Popup booking selalu punya peta ringkasan (tersembunyi), jadi yang dicek adalah blok lokasi acuan penjual
        $this->get(route('product.show', $product))->assertOk()
            ->assertDontSee('Lokasi acuan COD penjual')
            ->assertDontSee('data-seller-lat=', false);
    }

    public function test_active_booking_shows_seller_cod_point(): void
    {
        $this->sellerWithPoint();
        $buyer = User::factory()->create(['role' => 'pembeli']);
        $product = Product::create(['user_id' => $this->seller->id, 'title' => 'Kamera Analog', 'price' => 1250000, 'status' => 'Booked']);
        Booking::create([
            'product_id' => $product->id, 'user_id' => $buyer->id, 'qr_token' => 'TL-PIN00001',
            'cod_location' => 'Mega Mall', 'cod_schedule' => now()->addDay(), 'status_cod' => 'Pending',
        ]);

        $this->actingAs($buyer)->get(route('bookings.index'))->assertOk()
            ->assertSee('Lokasi acuan COD penjual')
            ->assertSee('Buka di Google Maps');
    }
}

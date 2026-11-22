<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Product;
use App\Models\User;
use App\Support\CodSpots;
use App\Support\SellerLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Saran tempat COD yang adil (config thriftlo.cod_spots) dan aturan keadilan jarak
 * (cod_point.max_each_km) untuk titik ketemuan booking.
 */
class CodSpotsTest extends TestCase
{
    use RefreshDatabase;

    private const SELLER = [1.1301, 104.0529];    // Batam Centre
    private const BALOI = [1.1300, 104.0200];     // 3.7 km dari penjual
    private const TIBAN = [1.1130, 103.9590];     // 10.6 km dari penjual (masih ≤ 2 x 6 km)
    private const BATU_AJI = [1.0437, 103.9806];  // 12.5 km dari penjual (> 2 x 6 km)

    private static function point(array $p): array
    {
        return ['lat' => $p[0], 'lng' => $p[1]];
    }

    // Tempat buatan dengan jarak yang mudah dihitung: penjual (1.13, 104.05), pembeli (1.13, 104.00), terpisah 5.6 km
    private function useTestSpots(): void
    {
        config(['thriftlo.cod_spots' => [
            ['name' => 'Dekat Penjual',   'lat' => 1.13, 'lng' => 104.045], // penjual 0.6, pembeli 5.0 → selisih 4.4
            ['name' => 'Terlalu Jauh',    'lat' => 1.13, 'lng' => 104.10],  // pembeli 11.1 km → tidak disarankan
            ['name' => 'Tengah Utara',    'lat' => 1.15, 'lng' => 104.025], // 3.6 / 3.6 → selisih 0, total 7.2
            ['name' => 'Tengah',          'lat' => 1.13, 'lng' => 104.025], // 2.8 / 2.8 → selisih 0, total 5.6
            ['name' => 'Jauh Dua-duanya', 'lat' => 1.20, 'lng' => 104.025], // 7.8 / 7.8 → tidak disarankan
        ]]);
    }

    // --- Pemilihan saran ---

    public function test_suggestions_are_sorted_by_farthest_distance_first(): void
    {
        $this->useTestSpots();

        $result = CodSpots::suggest(['lat' => 1.13, 'lng' => 104.00], ['lat' => 1.13, 'lng' => 104.05]);

        $this->assertSame(CodSpots::MODE_FAIR, $result['mode']);
        $this->assertFalse($result['too_far']);
        // Jarak pihak terjauh: Tengah 2.8 < Tengah Utara 3.6 < Dekat Penjual 5.0
        $this->assertSame(['Tengah', 'Tengah Utara', 'Dekat Penjual'], array_column($result['suggestions'], 'name'));
        $this->assertSame(
            ['seller_km' => 2.8, 'buyer_km' => 2.8, 'diff_km' => 0.0],
            array_intersect_key($result['suggestions'][0], array_flip(['seller_km', 'buyer_km', 'diff_km']))
        );
        $this->assertSame(4.4, $result['suggestions'][2]['diff_km']);
    }

    public function test_farthest_distance_outranks_difference_and_difference_breaks_ties(): void
    {
        // Penjual (1.13, 104.05), pembeli (1.13, 104.00)
        config(['thriftlo.cod_spots' => [
            ['name' => 'Sama-sama Jauh', 'lat' => 1.175, 'lng' => 104.025],  // 5.7 / 5.7 → terjauh 5.7, selisih 0
            ['name' => 'Dekat Timpang',  'lat' => 1.13,  'lng' => 104.035],  // 1.7 / 3.9 → terjauh 3.9, selisih 2.2
            ['name' => 'Seri Timpang',   'lat' => 1.13,  'lng' => 104.0176], // 3.6 / 2.0 → terjauh 3.6, selisih 1.6
            ['name' => 'Seri Seimbang',  'lat' => 1.15,  'lng' => 104.025],  // 3.6 / 3.6 → terjauh 3.6, selisih 0
        ]]);

        $result = CodSpots::suggest(['lat' => 1.13, 'lng' => 104.00], ['lat' => 1.13, 'lng' => 104.05], 10);

        // "Sama-sama Jauh" punya selisih terkecil tapi paling belakang karena kedua pihak harus menempuh 5.7 km;
        // dua tempat dengan jarak terjauh sama (3.6) diurutkan dari selisih terkecil
        $this->assertSame(['Seri Seimbang', 'Seri Timpang', 'Dekat Timpang', 'Sama-sama Jauh'], array_column($result['suggestions'], 'name'));
    }

    public function test_suggestions_never_exceed_max_each_km_for_either_party(): void
    {
        $this->useTestSpots();

        $names = array_column(CodSpots::suggest(['lat' => 1.13, 'lng' => 104.00], ['lat' => 1.13, 'lng' => 104.05], 10)['suggestions'], 'name');

        $this->assertNotContains('Terlalu Jauh', $names);
        $this->assertNotContains('Jauh Dua-duanya', $names);
    }

    public static function buyerPositions(): array
    {
        return [
            'Baloi'      => [self::BALOI],
            'Tiban'      => [self::TIBAN],
            'Nagoya'     => [[1.1442, 104.0109]],
            'Batam Kota' => [[1.1185, 104.0445]],
        ];
    }

    #[DataProvider('buyerPositions')]
    public function test_real_spots_respect_max_each_km_and_limit(array $buyer): void
    {
        $result = CodSpots::suggest(self::point($buyer), self::point(self::SELLER));

        $this->assertLessThanOrEqual(3, count($result['suggestions']));
        foreach ($result['suggestions'] as $s) {
            $this->assertLessThanOrEqual(6.0, $s['seller_km'], $s['name']);
            $this->assertLessThanOrEqual(6.0, $s['buyer_km'], $s['name']);
        }
        // Urutan: jarak pihak terjauh tidak pernah mengecil ke belakang
        $farthest = array_map(fn ($s) => max($s['seller_km'], $s['buyer_km']), $result['suggestions']);
        $sorted = $farthest;
        sort($sorted);
        $this->assertSame($sorted, $farthest);
    }

    public function test_max_each_km_comes_from_config(): void
    {
        $this->useTestSpots();
        config(['thriftlo.cod_point.max_each_km' => 3]);

        // Tengah Utara (3.6 km) dan Dekat Penjual (pembeli 5.0 km) sekarang terlalu jauh
        $names = array_column(CodSpots::suggest(['lat' => 1.13, 'lng' => 104.00], ['lat' => 1.13, 'lng' => 104.05])['suggestions'], 'name');
        $this->assertSame(['Tengah'], $names);
    }

    public function test_without_buyer_location_suggestions_are_nearest_to_seller(): void
    {
        $this->useTestSpots();

        $result = CodSpots::suggest(null, ['lat' => 1.13, 'lng' => 104.05]);

        $this->assertSame(CodSpots::MODE_SELLER, $result['mode']);
        $this->assertSame(['Dekat Penjual', 'Tengah', 'Tengah Utara'], array_column($result['suggestions'], 'name'));
        $this->assertNull($result['suggestions'][0]['buyer_km']);
        $this->assertStringContainsString('Jarak ke lokasimu belum diketahui', $result['message']);
    }

    public function test_without_seller_reference_suggestions_are_nearest_to_map_center_without_distance_check(): void
    {
        config(['thriftlo.cod_point.center' => ['lat' => 1.20, 'lng' => 104.025]]);
        $this->useTestSpots();

        $result = CodSpots::suggest(['lat' => 1.13, 'lng' => 104.00], null);

        $this->assertSame(CodSpots::MODE_CENTER, $result['mode']);
        // "Jauh Dua-duanya" tepat di pusat peta: tetap disarankan karena pengecekan jarak dilewati
        $this->assertSame(['Jauh Dua-duanya', 'Tengah Utara', 'Tengah'], array_column($result['suggestions'], 'name'));
        $this->assertNull($result['suggestions'][0]['seller_km']);
    }

    // --- Pembeli dan penjual terlalu jauh untuk COD ---

    public function test_buyer_and_seller_too_far_apart_get_no_suggestions(): void
    {
        $result = CodSpots::suggest(self::point(self::BATU_AJI), self::point(self::SELLER));

        $this->assertTrue($result['too_far']);
        $this->assertSame(12.5, $result['buyer_seller_km']);
        $this->assertSame([], $result['suggestions']);
        $this->assertSame('Jarak kalian terlalu jauh untuk COD, gunakan pengiriman', $result['message']);
    }

    public function test_too_far_limit_is_twice_max_each_km(): void
    {
        // Ke timur di khatulistiwa: 1° = 111.195 km → 12.0 km masih boleh, 12.1 km terlalu jauh
        $this->assertFalse(CodSpots::tooFarForCod(['lat' => 0, 'lng' => 0], ['lat' => 0, 'lng' => 0.1079]));
        $this->assertTrue(CodSpots::tooFarForCod(['lat' => 0, 'lng' => 0], ['lat' => 0, 'lng' => 0.1088]));
        $this->assertFalse(CodSpots::tooFarForCod(null, ['lat' => 0, 'lng' => 50]));
    }

    // --- Validasi booking ---

    private function seller(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role' => 'penjual', 'seller_status' => 'verified', 'lokasi_lapak' => 'Batam Center',
            'cod_address' => 'Depan Mega Mall', 'cod_latitude' => self::SELLER[0], 'cod_longitude' => self::SELLER[1],
        ], $attributes));
    }

    private function product(?User $seller = null): Product
    {
        return Product::create([
            'user_id' => ($seller ?? $this->seller())->id, 'title' => 'Kemeja Flanel', 'price' => 85000,
            'image_url' => 'products/x.jpg', 'status' => 'Available',
        ]);
    }

    private function book(Product $product, array $meeting, ?array $buyer = null)
    {
        $user = User::factory()->create(['role' => 'pembeli']);

        return $this->actingAs($user)->from(route('product.show', $product))->post(route('product.book', $product->id), array_filter([
            'idempotency_key'   => $this->formKey(),
            'lokasi_cod'        => 'Mega Mall Batam Centre',
            'meeting_latitude'  => $meeting[0],
            'meeting_longitude' => $meeting[1],
            'buyer_latitude'    => $buyer[0] ?? null,
            'buyer_longitude'   => $buyer[1] ?? null,
            'waktu_cod'         => now()->addDay()->toDateTimeString(),
            'qris_paid'         => 1,
        ], fn ($v) => $v !== null));
    }

    public function test_booking_rejected_when_buyer_and_seller_too_far_apart(): void
    {
        $product = $this->product();

        // Titik di sebelah penjual pun ditolak: tidak ada titik yang adil untuk keduanya
        $this->book($product, self::SELLER, self::BATU_AJI)
            ->assertRedirect(route('product.show', $product))
            ->assertSessionHasErrorsIn('booking', ['buyer_latitude' => 'Jarak kalian terlalu jauh untuk COD, gunakan pengiriman.']);

        $this->assertSame(0, Booking::count());
        $this->assertSame('Available', $product->fresh()->status);
    }

    public function test_booking_rejected_when_meeting_point_too_far_from_buyer(): void
    {
        $product = $this->product();
        $ocarina = [1.1430, 104.0620]; // 1.8 km dari penjual, 11.5 km dari pembeli di Tiban

        $this->book($product, $ocarina, self::TIBAN)
            ->assertSessionHasErrorsIn('booking', ['meeting_latitude']);
        $this->assertStringContainsString('terlalu jauh dari lokasimu', session('errors')->getBag('booking')->first('meeting_latitude'));
        $this->assertSame(0, Booking::count());
    }

    public function test_no_suggestion_when_no_spot_is_within_reach_of_both(): void
    {
        // Tiban ↔ Batam Centre 10.6 km: COD masih boleh, tapi tidak ada tempat di daftar yang ≤ 6 km dari keduanya
        $result = CodSpots::suggest(self::point(self::TIBAN), self::point(self::SELLER));

        $this->assertFalse($result['too_far']);
        $this->assertSame([], $result['suggestions']);
    }

    public function test_booking_with_top_fair_suggestion_is_accepted(): void
    {
        $product = $this->product();
        $top = CodSpots::suggest(self::point(self::BALOI), self::point(self::SELLER))['suggestions'][0];

        $this->book($product, [$top['lat'], $top['lng']], self::BALOI)->assertSessionHasNoErrors();

        $booking = Booking::sole();
        $this->assertSame($top['seller_km'], $booking->seller_distance_km);
        $this->assertSame($top['buyer_km'], $booking->buyer_distance_km);
    }

    public function test_booking_without_buyer_location_checks_seller_only(): void
    {
        // Pembeli menolak izin lokasi: saran dari penjual tetap bisa dipakai, hanya jarak ke penjual yang dicek
        $product = $this->product();
        $nearSeller = CodSpots::suggest(null, self::point(self::SELLER))['suggestions'][0];

        $this->book($product, [$nearSeller['lat'], $nearSeller['lng']])->assertSessionHasNoErrors();
        $booking = Booking::sole();
        $this->assertNull($booking->buyer_latitude);
        $this->assertNull($booking->buyer_distance_km);
        $this->assertSame($nearSeller['seller_km'], $booking->seller_distance_km);

        // Titik di Tiban (10.6 km dari penjual) tetap ditolak walau lokasi pembeli tidak diketahui
        $this->book($other = $this->product(), self::TIBAN)
            ->assertSessionHasErrorsIn('booking', ['meeting_latitude']);
        $this->assertSame('Available', $other->fresh()->status);
    }

    // --- Endpoint saran untuk popup ---

    public function test_suggestion_endpoint_returns_fair_suggestions(): void
    {
        $product = $this->product();

        $this->getJson(route('product.codSuggestions', [$product, 'lat' => self::BALOI[0], 'lng' => self::BALOI[1]]))
            ->assertOk()
            ->assertJsonPath('mode', 'fair')
            ->assertJsonPath('too_far', false)
            ->assertJsonPath('buyer_seller_km', 3.7)
            ->assertJsonCount(3, 'suggestions')
            ->assertJsonStructure(['suggestions' => [['name', 'lat', 'lng', 'seller_km', 'buyer_km', 'diff_km']]])
            ->assertJsonMissingPath('suggestions.0._center_km');
    }

    public function test_suggestion_endpoint_without_buyer_location_uses_seller_mode(): void
    {
        $this->getJson(route('product.codSuggestions', $this->product()))
            ->assertOk()
            ->assertJsonPath('mode', 'seller')
            ->assertJsonPath('suggestions.0.buyer_km', null);
    }

    public function test_suggestion_endpoint_reports_too_far(): void
    {
        $this->getJson(route('product.codSuggestions', [$this->product(), 'lat' => self::BATU_AJI[0], 'lng' => self::BATU_AJI[1]]))
            ->assertOk()
            ->assertJsonPath('too_far', true)
            ->assertJsonPath('message', 'Jarak kalian terlalu jauh untuk COD, gunakan pengiriman')
            ->assertJsonCount(0, 'suggestions');
    }

    public function test_suggestion_endpoint_rejects_half_coordinates_and_non_cod_products(): void
    {
        $this->getJson(route('product.codSuggestions', [$this->product(), 'lat' => 1.13]))->assertUnprocessable();

        $outside = $this->product($this->seller(['lokasi_lapak' => 'Luar Batam', 'kota_lapak' => 'Medan']));
        $this->getJson(route('product.codSuggestions', $outside))->assertNotFound();
    }

    // --- Data tempat di config ---

    public function test_configured_cod_spots_are_valid(): void
    {
        $spots = config('thriftlo.cod_spots');

        $this->assertGreaterThanOrEqual(15, count($spots));
        $this->assertSame(count($spots), count(array_unique(array_column($spots, 'name'))));
        foreach (['Mega Mall Batam Centre', 'Nagoya Hill Shopping Mall', 'BCS Mall', 'Panbil Mall', 'Grand Batam Mall', 'Pasar Botania'] as $name) {
            $this->assertContains($name, array_column($spots, 'name'));
        }
        foreach ($spots as $spot) {
            $this->assertLessThanOrEqual(60, mb_strlen($spot['name']), $spot['name']);
            $this->assertTrue(SellerLocation::isInBatamArea($spot['lat'], $spot['lng']), $spot['name'] . ' di luar wilayah Batam');
        }
    }
}

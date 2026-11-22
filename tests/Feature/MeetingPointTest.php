<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Product;
use App\Models\User;
use App\Support\GeoPoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Titik ketemuan COD usulan pembeli di popup booking: wilayah Batam, jarak maksimal dari lokasi
 * acuan penjual (cod_point.max_km), keterangan 60 karakter, dan tampilan di dashboard/Tiket Saya.
 */
class MeetingPointTest extends TestCase
{
    use RefreshDatabase;

    // Lokasi acuan penjual: Batam Centre
    private const SELLER = [1.1301, 104.0529];
    // Titik yang diketahui: Nagoya 4.9 km dari Batam Centre, Batu Aji 12.5 km
    private const NAGOYA = [1.1442, 104.0109];
    private const BATU_AJI = [1.0437, 103.9806];

    private User $seller;
    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seller = User::factory()->create([
            'role' => 'penjual', 'seller_status' => 'verified', 'nama_toko' => 'Lapak Centre', 'lokasi_lapak' => 'Batam Center',
            'cod_address' => 'Depan Mega Mall', 'cod_latitude' => self::SELLER[0], 'cod_longitude' => self::SELLER[1],
        ]);
        $this->buyer = User::factory()->create(['role' => 'pembeli', 'name' => 'Rina Pembeli']);
    }

    private function product(?User $owner = null): Product
    {
        return Product::create([
            'user_id' => ($owner ?? $this->seller)->id, 'title' => 'Jaket Denim', 'price' => 150000,
            'image_url' => 'products/x.jpg', 'status' => 'Available',
        ]);
    }

    private function book(Product $product, array $overrides = [])
    {
        return $this->actingAs($this->buyer)->from(route('product.show', $product))->post(route('product.book', $product->id), array_merge([
            'idempotency_key'   => $this->formKey(),
            'lokasi_cod'        => 'Depan Nagoya Hill',
            'meeting_latitude'  => self::NAGOYA[0],
            'meeting_longitude' => self::NAGOYA[1],
            'waktu_cod'         => now()->addDay()->toDateTimeString(),
            'qris_paid'         => 1,
        ], $overrides));
    }

    private function assertNotBooked(Product $product): void
    {
        $this->assertSame(0, Booking::count());
        $this->assertSame('Available', $product->fresh()->status);
    }

    // --- Perhitungan jarak (Haversine) ---

    public function test_haversine_distance_for_known_points(): void
    {
        // 1 derajat di khatulistiwa = 2πR/360 = 111.19 km
        $this->assertEqualsWithDelta(111.195, GeoPoint::distanceKm(0, 0, 0, 1), 0.001);
        $this->assertEqualsWithDelta(111.195, GeoPoint::distanceKm(0, 0, 1, 0), 0.001);
        // Batam Centre → Nagoya ≈ 4.93 km, ditampilkan 4.9 km
        $km = GeoPoint::distanceKm(...self::SELLER, ...self::NAGOYA);
        $this->assertEqualsWithDelta(4.925, $km, 0.001);
        $this->assertSame(4.9, GeoPoint::roundKm($km));
        // Simetris dan nol untuk titik yang sama
        $this->assertEqualsWithDelta($km, GeoPoint::distanceKm(...self::NAGOYA, ...self::SELLER), 1e-9);
        $this->assertSame(0.0, GeoPoint::distanceKm(...self::SELLER, ...self::SELLER));
        $this->assertSame(12.5, GeoPoint::roundKm(GeoPoint::distanceKm(...self::SELLER, ...self::BATU_AJI)));
    }

    // --- Booking dengan titik ketemuan ---

    public function test_booking_saves_meeting_point_buyer_location_and_distances(): void
    {
        $product = $this->product();

        $this->book($product, ['buyer_latitude' => self::BATU_AJI[0], 'buyer_longitude' => self::BATU_AJI[1]])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('bookings.index'));

        $booking = Booking::sole();
        $this->assertSame('Depan Nagoya Hill', $booking->cod_location);
        $this->assertSame(self::NAGOYA, [$booking->meeting_latitude, $booking->meeting_longitude]);
        $this->assertSame(self::BATU_AJI, [$booking->buyer_latitude, $booking->buyer_longitude]);
        $this->assertSame(4.9, $booking->seller_distance_km);
        $this->assertSame(GeoPoint::roundKm(GeoPoint::distanceKm(...self::NAGOYA, ...self::BATU_AJI)), $booking->buyer_distance_km);
        $this->assertSame('Booked', $product->fresh()->status);
    }

    public function test_distances_are_computed_on_server_not_trusted_from_browser(): void
    {
        $this->book($this->product(), ['seller_distance_km' => 0.1, 'buyer_distance_km' => 0.1])->assertSessionHasNoErrors();

        $booking = Booking::sole();
        $this->assertSame(4.9, $booking->seller_distance_km);
        $this->assertNull($booking->buyer_distance_km);
    }

    public function test_buyer_location_is_optional(): void
    {
        $this->book($this->product())->assertSessionHasNoErrors();

        $booking = Booking::sole();
        $this->assertNull($booking->buyer_latitude);
        $this->assertNull($booking->buyer_distance_km);
    }

    public function test_half_buyer_location_is_rejected(): void
    {
        $product = $this->product();

        $this->book($product, ['buyer_latitude' => self::NAGOYA[0]])->assertSessionHasErrorsIn('booking', ['buyer_longitude']);
        $this->assertNotBooked($product);
    }

    public function test_missing_meeting_point_is_rejected(): void
    {
        $product = $this->product();

        $this->book($product, ['meeting_latitude' => null, 'meeting_longitude' => null])
            ->assertSessionHasErrorsIn('booking', ['meeting_latitude' => 'Pasang pin titik ketemuan di peta dulu.']);
        $this->assertNotBooked($product);
    }

    // --- Wilayah Batam ---

    public static function outsideBatam(): array
    {
        return [
            'Jakarta'               => [-6.1754, 106.8272],
            'Marina Bay, Singapura' => [1.2834, 103.8607],
            'Tanjung Uban, Bintan'  => [1.0667, 104.2167],
        ];
    }

    #[DataProvider('outsideBatam')]
    public function test_meeting_point_outside_batam_is_rejected(float $lat, float $lng): void
    {
        $product = $this->product();

        $this->book($product, ['meeting_latitude' => $lat, 'meeting_longitude' => $lng])
            ->assertRedirect(route('product.show', $product))
            ->assertSessionHasErrorsIn('booking', ['meeting_latitude' => 'Titik ketemuan harus berada di wilayah Batam.']);
        $this->assertNotBooked($product);
    }

    // --- Jarak maksimal dari lokasi acuan penjual ---

    public function test_meeting_point_farther_than_max_km_is_rejected(): void
    {
        $product = $this->product();

        $this->book($product, ['meeting_latitude' => self::BATU_AJI[0], 'meeting_longitude' => self::BATU_AJI[1]])
            ->assertSessionHasErrorsIn('booking', [
                'meeting_latitude' => 'Titik ketemuan terlalu jauh dari lokasi acuan penjual (12.5 km). Maksimal 5 km.',
            ]);
        $this->assertNotBooked($product);
    }

    public function test_max_km_uses_the_rounded_distance_shown_to_buyer(): void
    {
        // Ke utara sepanjang meridian: jarak = Δlat × 111.195 km
        $product = $this->product();
        $this->book($product, ['meeting_latitude' => self::SELLER[0] + 0.0453, 'meeting_longitude' => self::SELLER[1]]) // 5.04 → 5.0 km
            ->assertSessionHasNoErrors();
        $this->assertSame(5.0, Booking::sole()->seller_distance_km);

        $other = $this->product();
        $this->book($other, ['meeting_latitude' => self::SELLER[0] + 0.0459, 'meeting_longitude' => self::SELLER[1]]) // 5.10 → 5.1 km
            ->assertSessionHasErrorsIn('booking', ['meeting_latitude']);
        $this->assertSame('Available', $other->fresh()->status);
    }

    public function test_max_km_comes_from_config(): void
    {
        config(['thriftlo.cod_point.max_km' => 15]);

        $this->book($this->product(), ['meeting_latitude' => self::BATU_AJI[0], 'meeting_longitude' => self::BATU_AJI[1]])
            ->assertSessionHasNoErrors();
        $this->assertSame(12.5, Booking::sole()->seller_distance_km);
    }

    public function test_seller_without_reference_point_skips_distance_check_but_not_batam_check(): void
    {
        $seller = User::factory()->create(['role' => 'penjual', 'seller_status' => 'verified', 'lokasi_lapak' => 'Nagoya']);

        $this->book($this->product($seller), ['meeting_latitude' => self::BATU_AJI[0], 'meeting_longitude' => self::BATU_AJI[1]])
            ->assertSessionHasNoErrors();
        $this->assertNull(Booking::sole()->seller_distance_km);

        $outside = $this->product($seller);
        $this->book($outside, ['meeting_latitude' => -6.1754, 'meeting_longitude' => 106.8272])
            ->assertSessionHasErrorsIn('booking', ['meeting_latitude' => 'Titik ketemuan harus berada di wilayah Batam.']);
        $this->assertSame('Available', $outside->fresh()->status);
    }

    // --- Keterangan tempat ---

    public function test_note_of_sixty_characters_is_accepted(): void
    {
        $this->book($this->product(), ['lokasi_cod' => str_repeat('a', 60)])->assertSessionHasNoErrors();
        $this->assertSame(60, mb_strlen(Booking::sole()->cod_location));
    }

    public function test_note_of_sixty_one_characters_is_rejected(): void
    {
        $product = $this->product();

        $this->book($product, ['lokasi_cod' => str_repeat('a', 61)])
            ->assertSessionHasErrorsIn('booking', ['lokasi_cod' => 'Keterangan tempat maksimal 60 karakter.']);
        $this->assertNotBooked($product);
    }

    public function test_note_limit_counts_characters_not_bytes(): void
    {
        $this->book($this->product(), ['lokasi_cod' => str_repeat('é', 60)])->assertSessionHasNoErrors();
        $this->book($other = $this->product(), ['lokasi_cod' => str_repeat('é', 61)])->assertSessionHasErrorsIn('booking', ['lokasi_cod']);
        $this->assertSame('Available', $other->fresh()->status);
    }

    public function test_booking_error_is_shown_on_product_page(): void
    {
        $product = $this->product();
        $this->book($product, ['meeting_latitude' => -6.1754, 'meeting_longitude' => 106.8272]);

        $this->actingAs($this->buyer)->get(route('product.show', $product))->assertOk()
            ->assertSee('Booking COD belum dibuat:')
            ->assertSee('Titik ketemuan harus berada di wilayah Batam.');
    }

    // --- Popup ---

    public function test_popup_has_map_buttons_counter_and_seller_reference_point(): void
    {
        $product = $this->product();

        $this->actingAs($this->buyer)->get(route('product.show', $product))->assertOk()
            ->assertSee('id="meetingMap"', false)
            ->assertSee('Gunakan lokasi saya')
            ->assertSee('Titik tengah')
            ->assertSee('Pakai lokasi acuan penjual')
            ->assertSee('maxlength="60"', false)
            ->assertSee('placeholder="Depan Mega Mall"', false)
            ->assertSee('0/60')
            ->assertSee('data-max-km="5"', false)
            ->assertSee('data-seller-lat="1.1301"', false)
            ->assertSee('data-seller-lng="104.0529"', false);
    }

    public function test_popup_without_seller_reference_point_has_no_seller_coordinates(): void
    {
        $seller = User::factory()->create(['role' => 'penjual', 'seller_status' => 'verified', 'lokasi_lapak' => 'Nagoya']);

        $this->actingAs($this->buyer)->get(route('product.show', $this->product($seller)))->assertOk()
            ->assertSee('id="meetingMap"', false)
            ->assertDontSee('data-seller-lat=', false);
    }

    // --- Tampilan setelah booking ---

    public function test_buyer_ticket_and_seller_dashboard_show_meeting_point(): void
    {
        $this->book($this->product(), ['buyer_latitude' => self::BATU_AJI[0], 'buyer_longitude' => self::BATU_AJI[1]]);
        $booking = Booking::sole();
        $mapsUrl = 'https://www.google.com/maps/search/?api=1&amp;query=' . rawurlencode(self::NAGOYA[0] . ',' . self::NAGOYA[1]);
        $buyerKm = number_format($booking->buyer_distance_km, 1);

        $this->actingAs($this->buyer)->get(route('bookings.index'))->assertOk()
            ->assertSee('Titik ketemuan')
            ->assertSee('Depan Nagoya Hill')
            ->assertSee("Penjual 4.9 km · Kamu {$buyerKm} km")
            ->assertSee($mapsUrl, false);

        $this->actingAs($this->seller)->get(route('dashboard'))->assertOk()
            ->assertSee('Depan Nagoya Hill')
            ->assertSee("Penjual 4.9 km · Pembeli {$buyerKm} km")
            ->assertSee($mapsUrl, false);
    }

    // --- Booking lama (sebelum ada titik ketemuan) ---

    private function legacyBooking(): Booking
    {
        $product = $this->product();
        $product->update(['status' => 'Booked']);

        return Booking::create([
            'product_id' => $product->id, 'user_id' => $this->buyer->id, 'qr_token' => 'TL-LAMA0001',
            'cod_location' => 'Mega Mall Batam Centre, lantai dasar dekat pintu masuk utama sebelah ATM', // > 60 karakter, data lama
            'cod_schedule' => now()->addDay(), 'status_cod' => 'Pending',
            'booking_fee' => 2000, 'fee_status' => Booking::FEE_PAID, 'paid_at' => now(),
        ]);
    }

    public function test_legacy_booking_without_coordinates_still_displays(): void
    {
        $booking = $this->legacyBooking();
        $this->assertFalse($booking->hasMeetingPoint());
        $this->assertNull($booking->meetingMapsUrl());
        $this->assertNull($booking->meetingDistanceLabel());

        $this->actingAs($this->buyer)->get(route('bookings.index'))->assertOk()
            ->assertSee('TL-LAMA0001')
            ->assertSee('Mega Mall Batam Centre, lantai dasar')
            ->assertDontSee('Titik ketemuan</p>', false)
            // Seperti sebelumnya: lokasi acuan penjual tetap ditampilkan untuk booking lama
            ->assertSee('Lokasi acuan COD penjual');

        $this->actingAs($this->seller)->get(route('dashboard'))->assertOk()
            ->assertSee('Mega Mall Batam Centre, lantai dasar')
            ->assertDontSee('Penjual 4.9 km', false);
    }

    public function test_legacy_booking_can_still_be_completed_with_qr_token(): void
    {
        $booking = $this->legacyBooking();

        $this->actingAs($this->seller)->post(route('booking.verify'), ['qr_code_token' => 'TL-LAMA0001'])
            ->assertSessionHas('success');

        $this->assertSame('Completed', $booking->fresh()->status_cod);
        $this->assertSame('Sold Out', $booking->product->fresh()->status);
    }
}

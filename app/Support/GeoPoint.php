<?php

namespace App\Support;

/**
 * Perhitungan titik di peta untuk COD: jarak Haversine dan tautan Google Maps.
 */
class GeoPoint
{
    // Jari-jari rata-rata bumi (km)
    public const EARTH_RADIUS_KM = 6371.0;

    // Jarak garis lurus (great-circle) dalam km, tidak dibulatkan
    public static function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * self::EARTH_RADIUS_KM * asin(min(1.0, sqrt($a)));
    }

    // Satu angka desimal, sama dengan yang ditampilkan di popup booking
    public static function roundKm(float $km): float
    {
        return round($km, 1);
    }

    public static function googleMapsUrl(float $lat, float $lng): string
    {
        return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($lat . ',' . $lng);
    }
}

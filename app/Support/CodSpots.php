<?php

namespace App\Support;

/**
 * Saran titik ketemuan COD yang adil dari daftar tempat populer (config thriftlo.cod_spots), dan
 * aturan keadilan jarak (config thriftlo.cod_point.max_each_km). Dipakai oleh endpoint saran di
 * popup booking dan oleh validasi booking, jadi keduanya selalu memakai aturan yang sama.
 *
 * Titik ditulis sebagai ['lat' => float, 'lng' => float]. Jarak dibandingkan setelah dibulatkan
 * 1 desimal, sama dengan angka yang dilihat pembeli.
 */
class CodSpots
{
    public const MODE_FAIR = 'fair';       // lokasi pembeli & penjual diketahui: urut jarak terjauh terkecil, lalu selisih
    public const MODE_SELLER = 'seller';   // lokasi pembeli belum diketahui: urut terdekat dari penjual
    public const MODE_CENTER = 'center';   // penjual belum punya lokasi acuan: urut terdekat dari pusat peta

    public const TOO_FAR_MESSAGE = 'Jarak kalian terlalu jauh untuk COD, gunakan pengiriman';

    public static function maxEachKm(): float
    {
        return (float) config('thriftlo.cod_point.max_each_km');
    }

    /** @return list<array{name: string, lat: float, lng: float}> */
    public static function all(): array
    {
        return array_values(config('thriftlo.cod_spots', []));
    }

    public static function km(array $a, array $b): float
    {
        return GeoPoint::roundKm(GeoPoint::distanceKm($a['lat'], $a['lng'], $b['lat'], $b['lng']));
    }

    // Jarak langsung pembeli-penjual lebih dari 2 x max_each_km: tidak ada titik yang adil untuk keduanya
    public static function tooFarForCod(?array $buyer, ?array $seller): bool
    {
        return $buyer && $seller && self::km($buyer, $seller) > 2 * self::maxEachKm();
    }

    /**
     * Saran tempat, paling cocok di depan.
     *
     * @return array{mode: string, too_far: bool, message: ?string, buyer_seller_km: ?float,
     *               suggestions: list<array{name: string, lat: float, lng: float, seller_km: ?float, buyer_km: ?float, diff_km: ?float}>}
     */
    public static function suggest(?array $buyer, ?array $seller, ?int $limit = null): array
    {
        $limit ??= (int) config('thriftlo.cod_point.suggestion_limit');
        $max = self::maxEachKm();
        $mode = $seller ? ($buyer ? self::MODE_FAIR : self::MODE_SELLER) : self::MODE_CENTER;
        $buyerSellerKm = $buyer && $seller ? self::km($buyer, $seller) : null;

        if (self::tooFarForCod($buyer, $seller)) {
            return [
                'mode' => $mode, 'too_far' => true, 'message' => self::TOO_FAR_MESSAGE,
                'buyer_seller_km' => $buyerSellerKm, 'suggestions' => [],
            ];
        }

        $center = config('thriftlo.cod_point.center');
        $spots = array_map(function (array $spot) use ($buyer, $seller, $center) {
            $sellerKm = $seller ? self::km($spot, $seller) : null;
            $buyerKm = $buyer ? self::km($spot, $buyer) : null;

            return [
                'name'      => mb_substr($spot['name'], 0, (int) config('thriftlo.cod_point.meeting_note_max')),
                'lat'       => (float) $spot['lat'],
                'lng'       => (float) $spot['lng'],
                'seller_km' => $sellerKm,
                'buyer_km'  => $buyerKm,
                'diff_km'   => $sellerKm !== null && $buyerKm !== null ? round(abs($sellerKm - $buyerKm), 1) : null,
                // Hanya untuk mengurutkan; tidak dikirim ke browser
                '_center_km' => self::km($spot, $center),
            ];
        }, self::all());

        // Tempat yang terlalu jauh untuk salah satu pihak tidak disarankan (kecuali penjual belum punya lokasi acuan)
        if ($mode !== self::MODE_CENTER) {
            $spots = array_filter($spots, fn ($s) => $s['seller_km'] <= $max && ($s['buyer_km'] === null || $s['buyer_km'] <= $max));
        }

        usort($spots, match ($mode) {
            // Jarak pihak yang paling jauh dulu (tidak ada yang dirugikan), lalu selisih terkecil,
            // lalu total jarak, lalu nama supaya urutannya stabil. Hanya mengurutkan berdasarkan selisih
            // akan memilih tempat yang sama-sama jauh (mis. 6.0 / 5.8 km) di atas yang dekat (1.6 / 3.0 km).
            self::MODE_FAIR   => fn ($a, $b) => [max($a['seller_km'], $a['buyer_km']), $a['diff_km'], $a['seller_km'] + $a['buyer_km'], $a['name']]
                                            <=> [max($b['seller_km'], $b['buyer_km']), $b['diff_km'], $b['seller_km'] + $b['buyer_km'], $b['name']],
            self::MODE_SELLER => fn ($a, $b) => [$a['seller_km'], $a['name']] <=> [$b['seller_km'], $b['name']],
            self::MODE_CENTER => fn ($a, $b) => [$a['_center_km'], $a['name']] <=> [$b['_center_km'], $b['name']],
        });

        $suggestions = array_map(function ($s) {
            unset($s['_center_km']);

            return $s;
        }, array_slice($spots, 0, max(0, $limit)));

        return [
            'mode'            => $mode,
            'too_far'         => false,
            'message'         => $mode === self::MODE_SELLER ? 'Jarak ke lokasimu belum diketahui, jadi saran diurutkan dari yang terdekat dengan penjual.' : null,
            'buyer_seller_km' => $buyerSellerKm,
            'suggestions'     => $suggestions,
        ];
    }

    /**
     * Pesan error kalau titik ketemuan melanggar aturan keadilan, atau null kalau boleh.
     * Pihak yang lokasinya tidak diketahui dilewati.
     *
     * @return array{field: string, message: string}|null
     */
    public static function violation(array $meeting, ?array $buyer, ?array $seller): ?array
    {
        if (!SellerLocation::isInBatamArea($meeting['lat'], $meeting['lng'])) {
            return ['field' => 'meeting_latitude', 'message' => 'Titik ketemuan harus berada di wilayah Batam.'];
        }

        if (self::tooFarForCod($buyer, $seller)) {
            return ['field' => 'buyer_latitude', 'message' => self::TOO_FAR_MESSAGE . '.'];
        }

        $max = self::maxEachKm();
        $maxLabel = rtrim(rtrim(number_format($max, 1), '0'), '.');

        if ($seller && ($km = self::km($meeting, $seller)) > $max) {
            return ['field' => 'meeting_latitude', 'message' => sprintf(
                'Titik ketemuan terlalu jauh dari lokasi acuan penjual (%s km). Maksimal %s km untuk masing-masing pihak.',
                number_format($km, 1), $maxLabel
            )];
        }

        if ($buyer && ($km = self::km($meeting, $buyer)) > $max) {
            return ['field' => 'meeting_latitude', 'message' => sprintf(
                'Titik ketemuan terlalu jauh dari lokasimu (%s km). Maksimal %s km untuk masing-masing pihak.',
                number_format($km, 1), $maxLabel
            )];
        }

        return null;
    }
}

<?php

namespace App\Support;

/**
 * Pilihan lokasi lapak penjual: kawasan di Batam (boleh COD) atau "Luar Batam" (hanya pengiriman).
 */
class SellerLocation
{
    public const OUTSIDE_BATAM = 'Luar Batam';

    public const OTHER_BATAM = 'Batam lainnya';

    // Ejaan lain yang sering ditulis penjual -> pilihan resmi
    private const ALIASES = [
        'batam centre'   => 'Batam Center',
        'tj piayu'       => 'Tanjung Piayu',
        'piayu'          => 'Tanjung Piayu',
        'tj uncang'      => 'Tanjung Uncang',
        'batuaji'        => 'Batu Aji',
        'sungai beduk'   => 'Sei Beduk',
        'sungai panas'   => 'Sei Panas',
        'muka kuning'    => 'Mukakuning',
    ];

    /** @return list<string> Semua pilihan dropdown, "Luar Batam" paling akhir */
    public static function options(): array
    {
        return [...config('thriftlo.batam_areas'), self::OUTSIDE_BATAM];
    }

    public static function isBatam(?string $location): bool
    {
        return in_array($location, config('thriftlo.batam_areas'), true);
    }

    // "Nagoya" -> "Nagoya, Batam"; "Batam Center" dan "Batam lainnya" sudah jelas Batam; Luar Batam -> nama kotanya
    public static function label(?string $location, ?string $city = null): string
    {
        if (!$location) {
            return 'Lokasi belum diisi';
        }

        if ($location === self::OUTSIDE_BATAM && trim((string) $city) !== '') {
            return trim($city);
        }

        if ($location === self::OTHER_BATAM) {
            return 'Batam';
        }

        return self::isBatam($location) && !str_contains(mb_strtolower($location), 'batam')
            ? $location . ', Batam'
            : $location;
    }

    /**
     * Cocokkan lokasi teks bebas (data lama) ke pilihan yang paling sesuai.
     * Kawasan spesifik didahulukan, lalu teks yang hanya menyebut "Batam", sisanya "Luar Batam".
     */
    public static function match(?string $text): string
    {
        // Sudah berupa pilihan resmi (mis. migrasi dijalankan ulang): biarkan
        if (in_array($text, self::options(), true)) {
            return $text;
        }

        $normalized = ' ' . trim(preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower((string) $text))) . ' ';

        // "luar batam" menyebut kata Batam, tapi justru berarti di luar Batam
        if (str_contains($normalized, ' luar batam ')) {
            return self::OUTSIDE_BATAM;
        }

        $candidates = self::ALIASES;
        foreach (config('thriftlo.batam_areas') as $area) {
            if ($area !== self::OTHER_BATAM) {
                $candidates[mb_strtolower($area)] = $area;
            }
        }

        // Nama terpanjang dulu supaya "batam center" tidak kalah oleh pencocokan yang lebih umum
        uksort($candidates, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        foreach ($candidates as $needle => $area) {
            if (str_contains($normalized, ' ' . $needle . ' ')) {
                return $area;
            }
        }

        return str_contains($normalized, ' batam ') ? self::OTHER_BATAM : self::OUTSIDE_BATAM;
    }
}

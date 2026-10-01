<?php

namespace App\Support;

/**
 * Deteksi nomor HP/WhatsApp dan ajakan pindah ke aplikasi lain di chat,
 * supaya transaksi tetap di dalam thriftLO (terlindungi token QR COD).
 */
class ContactInfo
{
    public const WARNING = 'Demi keamanan, pesan berisi nomor HP/WhatsApp atau ajakan pindah ke aplikasi lain tidak bisa dikirim. Tetap ngobrol dan bertransaksi di thriftLO supaya kamu terlindungi token QR COD.';

    private const MASK = '[nomor disembunyikan]';

    private const PHONE_PATTERNS = [
        // Nomor HP Indonesia, boleh dipisah spasi/titik/strip: 0812 3456 7890, +62 812-3456-7890
        '/(?<!\d)(?:\+\s*62|62|0)[\s.\-]*8(?:[\s.\-]*\d){8,11}(?!\d)/',
        // Urutan 10-13 digit angka tanpa pemisah
        '/(?<!\d)\d{10,13}(?!\d)/',
    ];

    // "wa", "whatsapp" dan variasinya, "no hp", telegram. Salam "wa'alaikumsalam" tidak ikut terblokir.
    private const KEYWORD_PATTERN = '/\b(?:w\.?a(?![\'’`]|\s*alaikum)|wh?at\'?s\s*app?|was+ap+|telegram|(?:no|nomor|nomer)\.?\s*(?:hp|wa|telp|telepon|whatsapp))\b/iu';

    public static function contains(string $text): bool
    {
        foreach (self::PHONE_PATTERNS as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        return (bool) preg_match(self::KEYWORD_PATTERN, $text);
    }

    // Untuk pesan lama yang terkirim sebelum filter ada: nomornya disamarkan saat ditampilkan
    public static function mask(string $text): string
    {
        return preg_replace(self::PHONE_PATTERNS, self::MASK, $text);
    }
}

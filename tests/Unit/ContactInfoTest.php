<?php

namespace Tests\Unit;

use App\Support\ContactInfo;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ContactInfoTest extends TestCase
{
    public static function blockedMessages(): array
    {
        return [
            'nomor polos'             => ['hubungi 081234567890 ya'],
            'nomor dipisah spasi'     => ['0812 3456 7890'],
            'nomor dipisah strip'     => ['0812-3456-789'],
            'format +62'              => ['+62 812-3456-7890'],
            'format 62 tanpa plus'    => ['6281234567890'],
            'deret 10 digit'          => ['kode 1234567890'],
            'kata wa'                 => ['chat aku di wa aja'],
            'wa huruf besar'          => ['WA aja kak'],
            'w.a'                     => ['lanjut w.a ya'],
            'whatsapp'                => ['ada whatsapp?'],
            'watsap'                  => ['watsap aja biar cepet'],
            'wasap'                   => ['wasapp dong'],
            'wa.me'                   => ['wa.me/628123'],
            'no hp'                   => ['minta no hp nya kak'],
            'nomor wa'                => ['nomor wa kamu berapa'],
            'telegram'                => ['pindah telegram yuk'],
        ];
    }

    public static function allowedMessages(): array
    {
        return [
            'tanya kondisi'           => ['Halo, barangnya masih ada? Ada minus?'],
            'harga rupiah'            => ['Harganya Rp1.500.000 ya kak'],
            'jadwal COD'              => ['COD tanggal 12-10-2026 jam 08.30 di Mega Mall'],
            'salam wa\'alaikum'       => ["Wa'alaikumsalam kak"],
            'salam wa alaikum'        => ['wa alaikum salam'],
            'kata mengandung wa'      => ['wah bagus, warnanya awet'],
            'token QR'                => ['tokenku TL-AB12CD34'],
            'ukuran'                  => ['ukuran 42, panjang 70 cm'],
        ];
    }

    #[DataProvider('blockedMessages')]
    public function test_contact_info_is_detected(string $text): void
    {
        $this->assertTrue(ContactInfo::contains($text), "Seharusnya diblokir: {$text}");
    }

    #[DataProvider('allowedMessages')]
    public function test_normal_messages_are_allowed(string $text): void
    {
        $this->assertFalse(ContactInfo::contains($text), "Seharusnya lolos: {$text}");
    }

    public function test_mask_hides_phone_numbers_only(): void
    {
        $this->assertSame(
            'WA aku [nomor disembunyikan] ya, harga Rp150.000',
            ContactInfo::mask('WA aku 0812 3456 7890 ya, harga Rp150.000')
        );
    }
}

<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class QrCode
{
    // QR sebagai SVG inline, dibuat di server agar isinya tidak dikirim ke layanan luar
    public static function svg(string $text, int $size = 200): string
    {
        $renderer = new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd());

        $svg = (new Writer($renderer))->writeString($text);

        // Buang deklarasi XML di awal karena SVG disisipkan langsung ke HTML
        return trim(preg_replace('/^<\?xml[^>]*\?>/', '', $svg));
    }
}

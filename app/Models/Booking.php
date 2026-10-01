<?php

namespace App\Models;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    // Tambahkan baris ini agar kolom-kolom tabel bisa disimpan
    protected $fillable = [
        'product_id',
        'user_id',
        'qr_token',
        'cod_location',
        'cod_schedule',
        'status_cod',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function review()
    {
        return $this->hasOne(Review::class);
    }

    // QR token COD sebagai SVG inline, dibuat di server agar token tidak dikirim ke layanan luar
    public function qrCodeSvg(int $size = 200): string
    {
        $renderer = new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd());

        $svg = (new Writer($renderer))->writeString($this->qr_token);

        // Buang deklarasi XML di awal karena SVG disisipkan langsung ke HTML
        return trim(preg_replace('/^<\?xml[^>]*\?>/', '', $svg));
    }
}

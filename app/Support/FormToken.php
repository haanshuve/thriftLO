<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Token formulir sekali pakai (kunci idempotensi). Setiap formulir booking, checkout, bayar
 * langganan, dan jual barang membawa UUID acak (<x-form-token />). Server menyimpannya di kolom
 * unik `idempotency_key` milik record yang dibuat, jadi kiriman ulang dengan token yang sama
 * mendapat hasil yang sama tanpa membuat duplikat.
 */
class FormToken
{
    public const FIELD = 'idempotency_key';

    public const EXPIRED_MESSAGE = 'Formulir ini sudah kedaluwarsa. Muat ulang halaman lalu coba lagi, ya.';

    // Token formulir yang sama dipakai untuk barang/akun lain (mis. halaman lama dari tombol Back)
    public const REUSED_MESSAGE = 'Formulir ini sudah pernah dipakai. Muat ulang halaman lalu coba lagi, ya.';

    /** @throws ValidationException */
    public static function validate(Request $request, string $errorBag = 'default'): string
    {
        $request->validateWithBag($errorBag, [self::FIELD => 'required|uuid'], [
            self::FIELD . '.required' => self::EXPIRED_MESSAGE,
            self::FIELD . '.uuid'     => self::EXPIRED_MESSAGE,
        ]);

        return (string) $request->input(self::FIELD);
    }
}

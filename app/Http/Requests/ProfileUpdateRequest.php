<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Support\SellerLocation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'phone_number' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\s-]+$/'],
        ];

        // Data toko hanya untuk penjual; wajib seperti saat registrasi
        if ($this->user()->role === 'penjual') {
            $rules['nama_toko'] = ['required', 'string', 'max:255'];
            $rules['lokasi_lapak'] = ['required', Rule::in(SellerLocation::options())];
            $rules['kota_lapak'] = ['nullable', 'required_if:lokasi_lapak,' . SellerLocation::OUTSIDE_BATAM, 'string', 'max:100'];
        }

        return $rules;
    }

    // Kota hanya disimpan untuk penjual Luar Batam; pindah ke kawasan Batam mengosongkannya
    public function validated($key = null, $default = null)
    {
        $data = parent::validated();

        if (array_key_exists('lokasi_lapak', $data)) {
            $data['kota_lapak'] = $data['lokasi_lapak'] === SellerLocation::OUTSIDE_BATAM ? trim((string) ($data['kota_lapak'] ?? '')) : null;
        }

        return data_get($data, $key, $default);
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.lowercase' => 'Email harus ditulis dengan huruf kecil.',
            'email.unique' => 'Email ini sudah dipakai akun lain.',
            'phone_number.regex' => 'Nomor WhatsApp hanya boleh berisi angka, spasi, tanda + atau -.',
            'phone_number.max' => 'Nomor WhatsApp maksimal 20 karakter.',
            'nama_toko.required' => 'Nama toko wajib diisi.',
            'lokasi_lapak.required' => 'Pilih lokasi lapak.',
            'lokasi_lapak.in' => 'Pilih lokasi lapak dari daftar yang tersedia.',
            'kota_lapak.required_if' => 'Tulis kota asal lapakmu (mis. Surabaya) supaya pembeli tahu barang dikirim dari mana.',
            'kota_lapak.max' => 'Nama kota maksimal 100 karakter.',
        ];
    }
}

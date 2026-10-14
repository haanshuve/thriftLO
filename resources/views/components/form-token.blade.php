{{-- Token formulir sekali pakai (kunci idempotensi), lihat App\Support\FormToken --}}
<input type="hidden" name="{{ \App\Support\FormToken::FIELD }}" value="{{ (string) \Illuminate\Support\Str::uuid() }}">

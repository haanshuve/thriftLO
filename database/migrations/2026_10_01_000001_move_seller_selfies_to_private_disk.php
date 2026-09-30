<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    // Selfie KYC lama tersimpan di disk public; pindahkan ke disk privat dengan path yang sama
    public function up(): void
    {
        $this->moveSelfies(from: 'public', to: 'local');
    }

    public function down(): void
    {
        $this->moveSelfies(from: 'local', to: 'public');
    }

    private function moveSelfies(string $from, string $to): void
    {
        $paths = DB::table('users')->whereNotNull('selfie_path')->pluck('selfie_path');

        foreach ($paths as $path) {
            if (Storage::disk($from)->exists($path) && !Storage::disk($to)->exists($path)) {
                Storage::disk($to)->put($path, Storage::disk($from)->get($path));
                Storage::disk($from)->delete($path);
            }
        }
    }
};

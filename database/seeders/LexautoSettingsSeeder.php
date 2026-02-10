<?php

namespace Database\Seeders;

use App\Models\Lexauto\LexautoSetting;
use Illuminate\Database\Seeder;

class LexautoSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'total_seats' => '100',
            'price' => '500',
            'reservation_minutes' => '30',
            'qr_image' => '',
            'google_sheet_url' => '',
        ];
        foreach ($defaults as $key => $value) {
            LexautoSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}

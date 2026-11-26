<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Setting;
use App\Services\Settings;
use Illuminate\Database\Seeder;

/**
 * Menyimpan nilai default App\Services\Settings::DEFAULTS ke tabel settings.
 */
class SettingSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Settings::DEFAULTS as $key => $value) {
            Setting::query()->firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}

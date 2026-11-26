<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Nilai default pengaturan (BUSINESS_RULES.md §1, §2.1 R4, §3, §4).
 */
class SettingSeeder extends Seeder
{
    /**
     * weekly_rest_days memakai nomor hari ISO-8601: 1 = Senin ... 7 = Minggu.
     *
     * @var array<string, mixed>
     */
    public const DEFAULTS = [
        'weekly_rest_days' => [7],
        'yesterday_cutoff_time' => '12:00',
        'daily_limit_hours' => 4,
        'weekly_limit_hours' => 18,
        'max_gross_hours' => 16,
    ];

    public function run(): void
    {
        foreach (self::DEFAULTS as $key => $value) {
            Setting::query()->firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}

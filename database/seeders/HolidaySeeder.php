<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Holiday;
use Illuminate\Database\Seeder;

/**
 * Contoh saja. Kalender libur resmi diisi Admin di Fase 5.
 */
class HolidaySeeder extends Seeder
{
    /**
     * @var array<string, string>
     */
    private const HOLIDAYS = [
        '2026-08-17' => 'Hari Kemerdekaan RI (contoh)',
        '2026-12-25' => 'Hari Raya Natal (contoh)',
    ];

    public function run(): void
    {
        foreach (self::HOLIDAYS as $date => $name) {
            // whereDate: SQLite menyimpan kolom date sebagai "Y-m-d 00:00:00".
            if (! Holiday::query()->whereDate('date', $date)->exists()) {
                Holiday::query()->create(['date' => $date, 'name' => $name]);
            }
        }
    }
}

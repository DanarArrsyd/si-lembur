<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DayType;
use App\Models\Holiday;
use Carbon\CarbonImmutable;

/**
 * Jenis hari dari tanggal lembur (BUSINESS_RULES.md §3). Tidak pernah diisi karyawan.
 */
class DayTypeResolver
{
    public function __construct(private readonly Settings $settings) {}

    public function resolve(CarbonImmutable $date): DayType
    {
        if (in_array($date->dayOfWeekIso, $this->settings->weeklyRestDays(), true)) {
            return DayType::HARI_LIBUR;
        }

        // whereDate: SQLite menyimpan kolom date sebagai "Y-m-d 00:00:00".
        if (Holiday::query()->whereDate('date', $date->toDateString())->exists()) {
            return DayType::HARI_LIBUR;
        }

        return DayType::HARI_KERJA;
    }
}

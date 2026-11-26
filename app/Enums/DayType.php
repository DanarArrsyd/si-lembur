<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Jenis hari, ditentukan otomatis dari tanggal lembur (BUSINESS_RULES.md §3).
 */
enum DayType: string
{
    use HasValues;

    case HARI_KERJA = 'HARI_KERJA';
    case HARI_LIBUR = 'HARI_LIBUR';

    public function label(): string
    {
        return match ($this) {
            self::HARI_KERJA => 'Hari kerja',
            self::HARI_LIBUR => 'Hari libur',
        };
    }
}

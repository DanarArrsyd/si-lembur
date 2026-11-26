<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Pilihan tanggal di form publik. Tanggal sebenarnya selalu dihitung dari jam server.
 */
enum DateChoice: string
{
    use HasValues;

    case TODAY = 'TODAY';
    case YESTERDAY = 'YESTERDAY';

    public function label(): string
    {
        return match ($this) {
            self::TODAY => 'Hari ini',
            self::YESTERDAY => 'Kemarin',
        };
    }
}

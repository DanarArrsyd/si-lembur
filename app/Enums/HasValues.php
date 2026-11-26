<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Daftar nilai backing enum, dipakai untuk definisi kolom ENUM di migrasi.
 */
trait HasValues
{
    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

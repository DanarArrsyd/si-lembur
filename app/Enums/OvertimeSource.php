<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Asal pengajuan: diisi karyawan lewat form publik atau oleh supervisor/admin.
 */
enum OvertimeSource: string
{
    use HasValues;

    case PUBLIC = 'PUBLIC';
    case SUPERVISOR = 'SUPERVISOR';

    public function label(): string
    {
        return match ($this) {
            self::PUBLIC => 'Karyawan',
            self::SUPERVISOR => 'Supervisor',
        };
    }
}

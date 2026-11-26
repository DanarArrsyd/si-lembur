<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Status pengajuan lembur (BUSINESS_RULES.md §6).
 */
enum OvertimeStatus: string
{
    use HasValues;

    case PENDING = 'PENDING';
    case APPROVED = 'APPROVED';
    case REJECTED = 'REJECTED';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Menunggu',
            self::APPROVED => 'Disetujui',
            self::REJECTED => 'Ditolak',
        };
    }
}

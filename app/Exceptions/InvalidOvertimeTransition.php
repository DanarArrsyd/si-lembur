<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\OvertimeStatus;
use DomainException;

/**
 * Aksi tidak sesuai status pengajuan saat ini (BUSINESS_RULES.md §6), biasanya karena
 * pengajuan sudah diproses orang lain di tab/HP lain.
 */
class InvalidOvertimeTransition extends DomainException
{
    public function __construct(public readonly OvertimeStatus $current)
    {
        $state = match ($current) {
            OvertimeStatus::PENDING => 'masih menunggu persetujuan',
            OvertimeStatus::APPROVED => 'sudah disetujui',
            OvertimeStatus::REJECTED => 'sudah ditolak',
        };

        parent::__construct("Pengajuan ini {$state}. Muat ulang halaman.");
    }
}

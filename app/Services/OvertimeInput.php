<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DateChoice;
use Carbon\CarbonImmutable;

/**
 * Isi form lembur. Form publik memakai $dateChoice dan bisa menyertakan $date
 * yang ditampilkan untuk mendeteksi perubahan hari. Form panel memakai $date.
 * Bentuk dasar (tipe, wajib diisi) divalidasi Form Request; aturan bisnis oleh OvertimeWorkflow.
 */
final readonly class OvertimeInput
{
    public function __construct(
        public int $employeeId,
        public int $departmentId,
        public ?DateChoice $dateChoice,
        public ?CarbonImmutable $date,
        public string $startTime,
        public string $endTime,
        public int $breakMinutes,
        public string $description,
    ) {}

    public function description(): string
    {
        return trim($this->description);
    }
}

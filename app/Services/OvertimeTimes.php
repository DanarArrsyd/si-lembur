<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonImmutable;

/**
 * Hasil OvertimeCalculator. Semua durasi dalam menit integer.
 */
final readonly class OvertimeTimes
{
    public function __construct(
        public CarbonImmutable $startAt,
        public CarbonImmutable $endAt,
        public int $grossMinutes,
        public int $breakMinutes,
        public int $totalMinutes,
    ) {}
}

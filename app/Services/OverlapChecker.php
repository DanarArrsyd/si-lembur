<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OvertimeStatus;
use App\Models\Overtime;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Cegah lembur beririsan untuk karyawan yang sama (BUSINESS_RULES.md §5, R6).
 * Bersebelahan tanpa irisan (17:00–19:00 lalu 19:00–21:00) boleh. REJECTED diabaikan.
 */
class OverlapChecker
{
    public function findConflict(int $employeeId, CarbonImmutable $startAt, CarbonImmutable $endAt, ?int $ignoreId = null): ?Overtime
    {
        return Overtime::query()
            ->where('employee_id', $employeeId)
            ->whereIn('status', [OvertimeStatus::PENDING, OvertimeStatus::APPROVED])
            ->where('start_at', '<', $endAt)
            ->where('end_at', '>', $startAt)
            ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
            ->orderBy('start_at')
            ->first();
    }

    /**
     * @throws ValidationException
     */
    public function assertNoConflict(int $employeeId, CarbonImmutable $startAt, CarbonImmutable $endAt, ?int $ignoreId = null): void
    {
        $conflict = $this->findConflict($employeeId, $startAt, $endAt, $ignoreId);

        if ($conflict !== null) {
            throw ValidationException::withMessages([
                'start_time' => "Sudah ada lembur {$conflict->number} di jam yang sama.",
            ]);
        }
    }
}

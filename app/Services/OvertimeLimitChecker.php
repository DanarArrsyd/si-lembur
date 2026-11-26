<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DayType;
use App\Enums\OvertimeStatus;
use App\Models\Overtime;
use Carbon\CarbonImmutable;

/**
 * Penanda batas lembur (BUSINESS_RULES.md §4). Hanya peringatan, tidak memblok.
 *
 * - Harian: total menit HARI_KERJA di tanggal yang sama > batas → semua pengajuan di tanggal itu ditandai.
 * - Mingguan (Senin–Minggu, menurut overtime_date): pengajuan HARI_KERJA diurutkan menurut start_at;
 *   pengajuan ditandai bila total berjalan sampai pengajuan itu > batas.
 * - Dihitung dari pengajuan PENDING + APPROVED. HARI_LIBUR selalu tanpa tanda dan tidak ikut dihitung.
 *   Pengajuan REJECTED tidak disentuh.
 */
class OvertimeLimitChecker
{
    public function __construct(private readonly Settings $settings) {}

    public function recalculateWeek(int $employeeId, CarbonImmutable $anyDateInWeek): void
    {
        $monday = $anyDateInWeek->startOfWeek(CarbonImmutable::MONDAY)->toDateString();
        $sunday = $anyDateInWeek->endOfWeek(CarbonImmutable::SUNDAY)->toDateString();

        $overtimes = Overtime::query()
            ->where('employee_id', $employeeId)
            ->whereIn('status', [OvertimeStatus::PENDING, OvertimeStatus::APPROVED])
            // whereDate: SQLite menyimpan kolom date sebagai "Y-m-d 00:00:00".
            ->whereDate('overtime_date', '>=', $monday)
            ->whereDate('overtime_date', '<=', $sunday)
            ->orderBy('start_at')
            ->orderBy('id')
            ->get();

        $workDays = $overtimes->filter(fn (Overtime $o) => $o->day_type === DayType::HARI_KERJA);

        $dailyTotals = $workDays
            ->groupBy(fn (Overtime $o) => $o->overtime_date->toDateString())
            ->map(fn ($group) => (int) $group->sum('total_minutes'));

        $dailyLimit = $this->settings->dailyLimitMinutes();
        $weeklyLimit = $this->settings->weeklyLimitMinutes();
        $runningTotal = 0;

        foreach ($overtimes as $overtime) {
            $exceedsDaily = false;
            $exceedsWeekly = false;

            if ($overtime->day_type === DayType::HARI_KERJA) {
                $runningTotal += $overtime->total_minutes;
                $exceedsDaily = $dailyTotals[$overtime->overtime_date->toDateString()] > $dailyLimit;
                $exceedsWeekly = $runningTotal > $weeklyLimit;
            }

            $overtime->exceeds_daily_limit = $exceedsDaily;
            $overtime->exceeds_weekly_limit = $exceedsWeekly;

            if ($overtime->isDirty()) {
                $overtime->saveQuietly();
            }
        }
    }
}

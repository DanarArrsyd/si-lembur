<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DayType;
use App\Enums\OvertimeStatus;
use App\Models\Overtime;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

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
        $overtimes = $this->week($employeeId, $anyDateInWeek)
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

    /** @return array{daily: bool, weekly: bool} */
    public function preview(int $employeeId, CarbonImmutable $date, CarbonImmutable $startAt, int $minutes, DayType $dayType, ?int $ignoreId = null): array
    {
        if ($dayType === DayType::HARI_LIBUR) {
            return ['daily' => false, 'weekly' => false];
        }

        $week = $this->week($employeeId, $date)
            ->where('day_type', DayType::HARI_KERJA)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId));

        $daily = (clone $week)->whereDate('overtime_date', $date->toDateString())->sum('total_minutes');
        $weekly = $week->where('start_at', '<=', $startAt)->sum('total_minutes');

        return [
            'daily' => $daily + $minutes > $this->settings->dailyLimitMinutes(),
            'weekly' => $weekly + $minutes > $this->settings->weeklyLimitMinutes(),
        ];
    }

    /** @return Builder<Overtime> */
    private function week(int $employeeId, CarbonImmutable $date): Builder
    {
        return Overtime::query()
            ->where('employee_id', $employeeId)
            ->whereIn('status', [OvertimeStatus::PENDING, OvertimeStatus::APPROVED])
            ->whereDate('overtime_date', '>=', $date->startOfWeek(CarbonImmutable::MONDAY)->toDateString())
            ->whereDate('overtime_date', '<=', $date->endOfWeek(CarbonImmutable::SUNDAY)->toDateString());
    }
}

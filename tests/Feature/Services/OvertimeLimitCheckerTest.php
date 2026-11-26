<?php

declare(strict_types=1);

use App\Enums\DayType;
use App\Enums\OvertimeStatus;
use App\Models\Employee;
use App\Models\Overtime;
use App\Services\OvertimeLimitChecker;
use Carbon\CarbonImmutable;

/**
 * Pengajuan mulai 17:00 di $date dengan $minutes total, tanpa istirahat.
 */
function overtimeOn(Employee $employee, string $date, int $minutes, array $attributes = []): Overtime
{
    $start = CarbonImmutable::parse("$date 17:00");

    return Overtime::factory()->for($employee)->create([
        'overtime_date' => $date,
        'start_at' => $start,
        'end_at' => $start->addMinutes($minutes),
        'break_minutes' => 0,
        'total_minutes' => $minutes,
        ...$attributes,
    ]);
}

function recalc(Employee $employee, string $date): void
{
    app(OvertimeLimitChecker::class)->recalculateWeek($employee->id, CarbonImmutable::parse($date));
}

/** Senin 09/11/2026 s.d. Sabtu 14/11/2026. */
const WORK_WEEK = ['2026-11-09', '2026-11-10', '2026-11-11', '2026-11-12', '2026-11-13', '2026-11-14'];

beforeEach(fn () => $this->employee = Employee::factory()->create());

it('flags a work day above 4 hours (H3)', function () {
    $o = overtimeOn($this->employee, '2026-11-10', 270);
    recalc($this->employee, '2026-11-10');

    expect($o->fresh()->exceeds_daily_limit)->toBeTrue()
        ->and($o->fresh()->exceeds_weekly_limit)->toBeFalse();
});

it('does not flag exactly 4 hours', function () {
    $o = overtimeOn($this->employee, '2026-11-10', 240);
    recalc($this->employee, '2026-11-10');

    expect($o->fresh()->exceeds_daily_limit)->toBeFalse();
});

it('sums every overtime on the same date for the daily limit', function () {
    $a = overtimeOn($this->employee, '2026-11-10', 180);
    $b = Overtime::factory()->for($this->employee)->create([
        'overtime_date' => '2026-11-10', 'start_at' => '2026-11-10 06:00', 'end_at' => '2026-11-10 08:00',
        'break_minutes' => 0, 'total_minutes' => 120,
    ]);
    recalc($this->employee, '2026-11-10');

    expect($a->fresh()->exceeds_daily_limit)->toBeTrue()
        ->and($b->fresh()->exceeds_daily_limit)->toBeTrue();
});

it('flags the overtime that pushes the week above 18 hours and those after it (H4)', function () {
    $week = collect(WORK_WEEK)->map(fn ($d, $i) => overtimeOn($this->employee, $d, $i === 5 ? 210 : 180));
    recalc($this->employee, '2026-11-12');

    expect($week->map(fn (Overtime $o) => $o->fresh()->exceeds_weekly_limit)->all())
        ->toBe([false, false, false, false, false, true]);
});

it('does not flag exactly 18 hours in a week', function () {
    $week = collect(WORK_WEEK)->map(fn ($d) => overtimeOn($this->employee, $d, 180));
    recalc($this->employee, '2026-11-09');

    expect($week->contains(fn (Overtime $o) => $o->fresh()->exceeds_weekly_limit))->toBeFalse();
});

it('ignores holiday overtime for both limits (H1)', function () {
    collect(WORK_WEEK)->each(fn ($d) => overtimeOn($this->employee, $d, 180));
    $sunday = overtimeOn($this->employee, '2026-11-15', 360, ['day_type' => DayType::HARI_LIBUR]);
    recalc($this->employee, '2026-11-15');

    expect($sunday->fresh()->exceeds_daily_limit)->toBeFalse()
        ->and($sunday->fresh()->exceeds_weekly_limit)->toBeFalse()
        ->and(Overtime::query()->where('exceeds_weekly_limit', true)->exists())->toBeFalse();
});

it('counts a Sunday-night overtime in the week of its start date', function () {
    $sunday = Overtime::factory()->for($this->employee)->create([
        'overtime_date' => '2026-11-15', 'start_at' => '2026-11-15 23:00', 'end_at' => '2026-11-16 02:00',
        'break_minutes' => 0, 'total_minutes' => 180, 'day_type' => DayType::HARI_KERJA,
    ]);
    $next = collect(['2026-11-16', '2026-11-17', '2026-11-18', '2026-11-19', '2026-11-20', '2026-11-21'])
        ->map(fn ($d) => overtimeOn($this->employee, $d, 180));
    recalc($this->employee, '2026-11-16');

    expect($next->contains(fn (Overtime $o) => $o->fresh()->exceeds_weekly_limit))->toBeFalse()
        ->and($sunday->fresh()->exceeds_weekly_limit)->toBeFalse();
});

it('ignores rejected overtimes and clears flags once the cause is rejected (H5)', function () {
    $early = overtimeOn($this->employee, '2026-11-09', 300);
    $week = collect(array_slice(WORK_WEEK, 1))->map(fn ($d) => overtimeOn($this->employee, $d, 180));
    recalc($this->employee, '2026-11-09');
    expect($week->last()->fresh()->exceeds_weekly_limit)->toBeTrue();

    $early->update(['status' => OvertimeStatus::REJECTED]);
    recalc($this->employee, '2026-11-09');

    expect($week->contains(fn (Overtime $o) => $o->fresh()->exceeds_weekly_limit))->toBeFalse();
});

it('does not count other employees', function () {
    $other = Employee::factory()->create();
    overtimeOn($other, '2026-11-10', 300);
    $mine = overtimeOn($this->employee, '2026-11-10', 180);
    recalc($this->employee, '2026-11-10');

    expect($mine->fresh()->exceeds_daily_limit)->toBeFalse();
});

<?php

declare(strict_types=1);

use App\Enums\OvertimeStatus;
use App\Models\Employee;
use App\Models\Overtime;
use App\Services\OverlapChecker;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

function existing(Employee $employee, string $start, string $end, OvertimeStatus $status): Overtime
{
    return Overtime::factory()->for($employee)->create([
        'number' => 'LBR-20261109-0007',
        'overtime_date' => substr($start, 0, 10),
        'start_at' => $start,
        'end_at' => $end,
        'status' => $status,
    ]);
}

function conflict(Employee $employee, string $start, string $end, ?int $ignoreId = null): ?Overtime
{
    return app(OverlapChecker::class)->findConflict($employee->id, CarbonImmutable::parse($start), CarbonImmutable::parse($end), $ignoreId);
}

beforeEach(fn () => $this->employee = Employee::factory()->create());

// BUSINESS_RULES.md §7.4
it('rejects an overlap with a pending overtime (O1)', function () {
    $o = existing($this->employee, '2026-11-09 17:00', '2026-11-09 20:00', OvertimeStatus::PENDING);

    expect(conflict($this->employee, '2026-11-09 19:00', '2026-11-09 21:00')?->id)->toBe($o->id);
});

it('allows back-to-back overtimes (O2)', function () {
    existing($this->employee, '2026-11-09 17:00', '2026-11-09 19:00', OvertimeStatus::APPROVED);

    expect(conflict($this->employee, '2026-11-09 19:00', '2026-11-09 21:00'))->toBeNull();
});

it('ignores rejected overtimes (O3)', function () {
    existing($this->employee, '2026-11-09 17:00', '2026-11-09 20:00', OvertimeStatus::REJECTED);

    expect(conflict($this->employee, '2026-11-09 17:00', '2026-11-09 20:00'))->toBeNull();
});

it('detects overlaps across dates (O4)', function () {
    existing($this->employee, '2026-11-09 23:00', '2026-11-10 02:00', OvertimeStatus::APPROVED);

    expect(conflict($this->employee, '2026-11-10 01:00', '2026-11-10 03:00'))->not->toBeNull();
});

it('does not conflict with itself when editing', function () {
    $o = existing($this->employee, '2026-11-09 17:00', '2026-11-09 20:00', OvertimeStatus::PENDING);

    expect(conflict($this->employee, '2026-11-09 17:30', '2026-11-09 20:30', $o->id))->toBeNull();
});

it('does not conflict with other employees', function () {
    existing(Employee::factory()->create(), '2026-11-09 17:00', '2026-11-09 20:00', OvertimeStatus::PENDING);

    expect(conflict($this->employee, '2026-11-09 17:00', '2026-11-09 20:00'))->toBeNull();
});

it('names the conflicting overtime in the error (R6)', function () {
    existing($this->employee, '2026-11-09 17:00', '2026-11-09 20:00', OvertimeStatus::PENDING);

    expect(fn () => app(OverlapChecker::class)->assertNoConflict(
        $this->employee->id, CarbonImmutable::parse('2026-11-09 18:00'), CarbonImmutable::parse('2026-11-09 19:00'),
    ))->toThrow(function (ValidationException $e) {
        expect($e->errors())->toBe(['start_time' => ['Sudah ada lembur LBR-20261109-0007 di jam yang sama.']]);
    });
});

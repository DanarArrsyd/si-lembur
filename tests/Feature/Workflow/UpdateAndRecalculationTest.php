<?php

declare(strict_types=1);

use App\Enums\DayType;
use App\Enums\OvertimeStatus;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Overtime;
use App\Models\User;
use App\Services\OvertimeInput;
use Carbon\CarbonImmutable;

beforeEach(function () {
    Carbon\Carbon::setTestNow('2026-11-20 09:00');
    $this->now = CarbonImmutable::parse('2026-11-20 09:00');
    $this->department = Department::factory()->create();
    $this->supervisor = User::factory()->supervisor($this->department)->create();
    $this->employee = Employee::factory()->for($this->department)->create();
});

function panelSubmit(object $test, string $date, string $start = '17:00', string $end = '20:00', bool $approve = false): Overtime
{
    $overtime = workflow()->submitPublic(
        overtimeInput($test->employee, ['startTime' => $start, 'endTime' => $end]),
        CarbonImmutable::parse("$date 23:30"),
    );

    return $approve ? workflow()->approve($test->supervisor, $overtime) : $overtime;
}

function editInput(Employee $employee, string $date, array $overrides = []): OvertimeInput
{
    return overtimeInput($employee, ['dateChoice' => null, 'date' => CarbonImmutable::parse($date), ...$overrides]);
}

it('edits a pending overtime and logs before and after', function () {
    $overtime = panelSubmit($this, '2026-11-19');
    AuditLog::query()->delete();

    $edited = workflow()->update($this->supervisor, $overtime, editInput($this->employee, '2026-11-19', [
        'endTime' => '21:00', 'description' => 'Packing ulang',
    ]), $this->now);

    expect($edited)
        ->total_minutes->toBe(240)
        ->description->toBe('Packing ulang')
        ->status->toBe(OvertimeStatus::PENDING)
        ->number->toBe($overtime->number)
        ->and(AuditLog::query()->sole())
        ->action->toBe('overtime.updated')
        ->before->toMatchArray(['total_minutes' => 180])
        ->after->toMatchArray(['total_minutes' => 240]);
});

it('does not conflict with itself when editing', function () {
    $overtime = panelSubmit($this, '2026-11-19');

    expect(workflow()->update($this->supervisor, $overtime, editInput($this->employee, '2026-11-19', ['startTime' => '17:30']), $this->now)->total_minutes)
        ->toBe(150);
});

it('refuses to change the employee', function () {
    $overtime = panelSubmit($this, '2026-11-19');
    $other = Employee::factory()->for($this->department)->create();

    expect(validationErrors(fn () => workflow()->update($this->supervisor, $overtime, editInput($other, '2026-11-19'), $this->now)))
        ->toBe(['employee_id' => ['Karyawan tidak bisa diganti. Tolak pengajuan ini lalu input ulang.']]);
});

it('recalculates both weeks when an edit moves an overtime to another week', function () {
    // Senin–Sabtu 09–14/11: 6 × 3 jam = tepat 18 jam, belum lewat batas.
    $lastWeek = collect(['2026-11-09', '2026-11-10', '2026-11-11', '2026-11-12', '2026-11-13', '2026-11-14'])
        ->map(fn ($d) => panelSubmit($this, $d, approve: true));
    $moving = panelSubmit($this, '2026-11-17', '06:00', '07:00');

    // Pindah ke Sabtu 14/11 pagi: total minggu lalu jadi 19 jam; lembur Sabtu sore yang melewati batas.
    $moving = workflow()->update($this->supervisor, $moving, editInput($this->employee, '2026-11-14', ['startTime' => '06:00', 'endTime' => '07:00']), $this->now);

    expect($moving->exceeds_weekly_limit)->toBeFalse()
        ->and($lastWeek->last()->fresh()->exceeds_weekly_limit)->toBeTrue();

    // Kembali ke minggu ini: minggu lalu kembali tepat 18 jam.
    workflow()->update($this->supervisor, $moving, editInput($this->employee, '2026-11-17', ['startTime' => '06:00', 'endTime' => '07:00']), $this->now);

    expect($lastWeek->contains(fn (Overtime $o) => $o->fresh()->exceeds_weekly_limit))->toBeFalse();
});

it('recalculates day type for pending overtimes only when the calendar changes', function () {
    $pending = panelSubmit($this, '2026-11-17');
    $approved = panelSubmit($this, '2026-11-17', '20:00', '21:00', approve: true);
    Holiday::factory()->create(['date' => '2026-11-17']);

    $changed = workflow()->recalculateDayTypes([CarbonImmutable::parse('2026-11-17')]);

    expect($changed)->toBe(1)
        ->and($pending->fresh()->day_type)->toBe(DayType::HARI_LIBUR)
        ->and($approved->fresh()->day_type)->toBe(DayType::HARI_KERJA);
});

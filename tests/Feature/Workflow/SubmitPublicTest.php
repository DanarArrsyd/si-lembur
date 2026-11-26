<?php

declare(strict_types=1);

use App\Enums\DateChoice;
use App\Enums\DayType;
use App\Enums\OvertimeSource;
use App\Enums\OvertimeStatus;
use App\Models\AuditLog;
use App\Models\Employee;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->now = CarbonImmutable::parse('2026-11-10 08:00');
    $this->employee = Employee::factory()->create();
});

it('stores an overnight overtime on its start date (PRD §8 #2)', function () {
    $overtime = workflow()->submitPublic(overtimeInput($this->employee, [
        'dateChoice' => DateChoice::YESTERDAY, 'startTime' => '23:00', 'endTime' => '02:00', 'breakMinutes' => 30,
    ]), $this->now);

    expect($overtime)
        ->overtime_date->toDateString()->toBe('2026-11-09')
        ->start_at->format('Y-m-d H:i')->toBe('2026-11-09 23:00')
        ->end_at->format('Y-m-d H:i')->toBe('2026-11-10 02:00')
        ->total_minutes->toBe(150)
        ->break_minutes->toBe(30)
        ->status->toBe(OvertimeStatus::PENDING)
        ->source->toBe(OvertimeSource::PUBLIC)
        ->number->toBe('LBR-20261110-0001')
        ->department_id->toBe($this->employee->department_id);
});

it('records the submission in the audit log without a user', function () {
    $this->app['request']->server->set('REMOTE_ADDR', '10.0.0.9');

    $overtime = workflow()->submitPublic(overtimeInput($this->employee), $this->now);

    $log = AuditLog::query()->sole();
    expect($log->action)->toBe('overtime.submitted')
        ->and($log->user_id)->toBeNull()
        ->and($log->auditable_id)->toBe($overtime->id)
        ->and($log->after['total_minutes'])->toBe(180)
        ->and($overtime->submitted_ip)->toBe('10.0.0.9');
});

it('rejects yesterday after the cutoff (PRD §8 #3)', function () {
    expect(validationErrors(fn () => workflow()->submitPublic(
        overtimeInput($this->employee, ['dateChoice' => DateChoice::YESTERDAY]),
        CarbonImmutable::parse('2026-11-10 12:01'),
    )))->toHaveKey('overtime_date');
});

it('rejects an overlap with the same employee (PRD §8 #5)', function () {
    $first = workflow()->submitPublic(overtimeInput($this->employee), $this->now);

    expect(validationErrors(fn () => workflow()->submitPublic(
        overtimeInput($this->employee, ['startTime' => '19:00', 'endTime' => '21:00']), $this->now,
    )))->toBe(['start_time' => ["Sudah ada lembur {$first->number} di jam yang sama."]]);
});

it('marks Sunday overtime as holiday without limit flags (PRD §8 #10)', function () {
    $overtime = workflow()->submitPublic(
        overtimeInput($this->employee, ['startTime' => '08:00', 'endTime' => '14:00']),
        CarbonImmutable::parse('2026-11-08 15:00'), // Minggu
    );

    expect($overtime->day_type)->toBe(DayType::HARI_LIBUR)
        ->and($overtime->exceeds_daily_limit)->toBeFalse()
        ->and($overtime->exceeds_weekly_limit)->toBeFalse();
});

it('flags but still stores a work day above the daily limit (H3)', function () {
    $overtime = workflow()->submitPublic(overtimeInput($this->employee, ['startTime' => '16:00', 'endTime' => '20:30']), $this->now);

    expect($overtime->exceeds_daily_limit)->toBeTrue()
        ->and($overtime->status)->toBe(OvertimeStatus::PENDING);
});

it('requires a date choice for public input', function () {
    expect(validationErrors(fn () => workflow()->submitPublic(overtimeInput($this->employee, ['dateChoice' => null]), $this->now)))
        ->toHaveKey('overtime_date');
});

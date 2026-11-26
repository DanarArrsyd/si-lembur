<?php

declare(strict_types=1);

use App\Enums\DayType;
use App\Enums\OvertimeSource;
use App\Enums\OvertimeStatus;
use App\Models\AuditLog;
use App\Models\Overtime;
use App\Models\Setting;

it('stores overtime datetimes as Jakarta wall-clock time', function () {
    $overtime = Overtime::factory()->create([
        'overtime_date' => '2026-11-09',
        'start_at' => '2026-11-09 23:00:00',
        'end_at' => '2026-11-10 02:00:00',
    ]);

    $fresh = $overtime->fresh();

    expect($fresh->start_at->format('Y-m-d H:i'))->toBe('2026-11-09 23:00')
        ->and($fresh->end_at->format('Y-m-d H:i'))->toBe('2026-11-10 02:00')
        ->and($fresh->overtime_date->format('Y-m-d'))->toBe('2026-11-09')
        ->and($fresh->start_at->getTimezone()->getName())->toBe('Asia/Jakarta');
});

it('casts status, source and day type to enums and minutes to integers', function () {
    $fresh = Overtime::factory()->create()->fresh();

    expect($fresh->status)->toBe(OvertimeStatus::PENDING)
        ->and($fresh->source)->toBe(OvertimeSource::PUBLIC)
        ->and($fresh->day_type)->toBe(DayType::HARI_KERJA)
        ->and($fresh->total_minutes)->toBeInt()
        ->and($fresh->exceeds_daily_limit)->toBeFalse();
});

it('snapshots the department of the employee', function () {
    $overtime = Overtime::factory()->create();

    expect($overtime->department_id)->toBe($overtime->employee->department_id);
});

it('stores settings and audit payloads as json', function () {
    Setting::query()->create(['key' => 'weekly_rest_days', 'value' => [7]]);
    $log = AuditLog::query()->create([
        'action' => 'overtime.approved',
        'before' => ['status' => 'PENDING'],
        'after' => ['status' => 'APPROVED'],
    ]);

    expect(Setting::query()->find('weekly_rest_days')->value)->toBe([7])
        ->and($log->fresh()->after)->toBe(['status' => 'APPROVED'])
        ->and($log->fresh()->created_at)->not->toBeNull();
});

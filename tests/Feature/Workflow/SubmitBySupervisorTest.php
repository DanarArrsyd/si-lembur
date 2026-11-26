<?php

declare(strict_types=1);

use App\Enums\OvertimeSource;
use App\Enums\OvertimeStatus;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use App\Services\OvertimeInput;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function () {
    Carbon\Carbon::setTestNow('2026-12-05 09:00');
    $this->now = CarbonImmutable::parse('2026-12-05 09:00');
    $this->department = Department::factory()->create();
    $this->employee = Employee::factory()->for($this->department)->create();
    $this->supervisor = User::factory()->supervisor($this->department)->create();
});

function panelInput(Employee $employee, string $date): OvertimeInput
{
    return overtimeInput($employee, ['dateChoice' => null, 'date' => CarbonImmutable::parse($date)]);
}

it('stores supervisor input as approved (PRD §8 #9)', function () {
    $overtime = workflow()->submitBySupervisor($this->supervisor, panelInput($this->employee, '2026-11-15'), $this->now);

    expect($overtime)
        ->status->toBe(OvertimeStatus::APPROVED)
        ->source->toBe(OvertimeSource::SUPERVISOR)
        ->created_by->toBe($this->supervisor->id)
        ->approved_by->toBe($this->supervisor->id)
        ->approved_at->format('Y-m-d H:i')->toBe('2026-12-05 09:00')
        ->overtime_date->toDateString()->toBe('2026-11-15')
        ->and(AuditLog::query()->sole())
        ->action->toBe('overtime.created_by_supervisor')
        ->user_id->toBe($this->supervisor->id);
});

it('applies the panel date rule (D6)', function () {
    expect(validationErrors(fn () => workflow()->submitBySupervisor($this->supervisor, panelInput($this->employee, '2026-10-20'), $this->now)))
        ->toHaveKey('overtime_date');
});

it('forbids supervisors from other departments (PRD §8 #6)', function () {
    $other = User::factory()->supervisor(Department::factory()->create())->create();

    expect(fn () => workflow()->submitBySupervisor($other, panelInput($this->employee, '2026-12-01'), $this->now))
        ->toThrow(AuthorizationException::class);
});

it('lets admins input for any department', function () {
    $admin = User::factory()->admin()->create();

    expect(workflow()->submitBySupervisor($admin, panelInput($this->employee, '2026-12-01'), $this->now)->status)
        ->toBe(OvertimeStatus::APPROVED);
});

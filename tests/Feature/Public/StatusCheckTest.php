<?php

declare(strict_types=1);

use App\Enums\OvertimeStatus;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Overtime;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-27 11:59', 'Asia/Jakarta'));
    $this->employee = Employee::factory()->create(['name' => 'Budi Santoso']);
    $this->statusUrl = '/api/status?employee_id='.$this->employee->id;
});

it('shows the status page with active departments and validates the prefilled employee', function () {
    $anotherDepartment = Department::factory()->create();
    Department::factory()->create(['is_active' => false]);

    $this->get('/status?department='.$this->employee->department_id.'&employee='.$this->employee->id)
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/status')
            ->has('departments', 2)
            ->where('prefillDepartmentId', $this->employee->department_id)
            ->where('prefillEmployee', [
                'id' => $this->employee->id,
                'nik' => $this->employee->nik,
                'name' => 'Budi Santoso',
            ]));

    $this->get('/status?department='.$anotherDepartment->id.'&employee='.$this->employee->id)
        ->assertInertia(fn (Assert $page) => $page
            ->where('prefillDepartmentId', $anotherDepartment->id)
            ->where('prefillEmployee', null));

    $this->get('/status?department=invalid&employee[]=1')
        ->assertInertia(fn (Assert $page) => $page
            ->where('prefillDepartmentId', null)
            ->where('prefillEmployee', null));
});

it('returns the latest records for one employee with the 60 day boundary inclusive', function () {
    Overtime::factory()->for($this->employee)->create([
        'number' => 'LBR-20261127-0001',
        'overtime_date' => '2026-11-27',
        'start_at' => '2026-11-27 03:00:00',
        'end_at' => '2026-11-27 04:30:00',
        'total_minutes' => 90,
        'status' => OvertimeStatus::REJECTED,
        'reject_reason' => 'Jam selesai salah.',
        'description' => 'Kegiatan internal.',
    ]);
    Overtime::factory()->for($this->employee)->create([
        'number' => 'LBR-20260928-0001',
        'overtime_date' => '2026-09-28',
    ]);
    Overtime::factory()->for($this->employee)->create([
        'number' => 'LBR-20260927-0001',
        'overtime_date' => '2026-09-27',
    ]);
    Overtime::factory()->create(['overtime_date' => '2026-11-27']);

    $this->getJson($this->statusUrl)->assertOk()
        ->assertJsonCount(2)
        ->assertJsonPath('1.number', 'LBR-20260928-0001')
        ->assertJsonPath('0', [
            'number' => 'LBR-20261127-0001',
            'date' => '2026-11-27',
            'start' => '2026-11-27 03:00',
            'end' => '2026-11-27 04:30',
            'total_minutes' => 90,
            'status' => 'REJECTED',
            'status_label' => 'Ditolak',
            'reject_reason' => 'Jam selesai salah.',
            'can_resubmit' => true,
        ])
        ->assertJsonMissingPath('0.description')
        ->assertJsonMissingPath('0.employee_id');
});

it('uses server date choices for rejected entries and hides reasons on other statuses', function () {
    foreach ([
        ['2026-11-27', OvertimeStatus::PENDING],
        ['2026-11-26', OvertimeStatus::REJECTED],
        ['2026-11-24', OvertimeStatus::REJECTED],
    ] as [$date, $status]) {
        Overtime::factory()->for($this->employee)->create([
            'overtime_date' => $date,
            'status' => $status,
            'reject_reason' => 'Alasan untuk pengajuan yang ditolak.',
        ]);
    }

    $this->getJson($this->statusUrl)->assertOk()
        ->assertJsonPath('0.can_resubmit', false)
        ->assertJsonPath('0.reject_reason', null)
        ->assertJsonPath('1.can_resubmit', true)
        ->assertJsonPath('2.can_resubmit', false);

    $this->travel(1)->minutes();
    $this->getJson($this->statusUrl)->assertOk()->assertJsonPath('1.can_resubmit', false);
});

it('returns an empty list only for a valid active employee without history', function () {
    $this->getJson($this->statusUrl)->assertOk()->assertExactJson([]);
    $this->getJson('/api/status')->assertUnprocessable()->assertJsonValidationErrors('employee_id');
    $this->getJson('/api/status?employee_id=99999999')->assertNotFound();

    $this->employee->update(['is_active' => false]);
    $this->getJson($this->statusUrl)->assertNotFound();

    $this->employee->update(['is_active' => true]);
    $this->employee->department->update(['is_active' => false]);
    $this->getJson($this->statusUrl)->assertNotFound();
});

it('limits status independently from employee lookup', function () {
    $lookup = '/api/departments/'.$this->employee->department_id.'/employees';
    $this->getJson($lookup)->assertOk();

    foreach (range(1, 60) as $_) {
        $this->getJson($this->statusUrl)->assertOk();
    }

    $this->getJson($this->statusUrl)->assertTooManyRequests();
    $this->getJson($lookup)->assertOk();
});

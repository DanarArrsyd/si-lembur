<?php

declare(strict_types=1);

use App\Enums\OvertimeStatus;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Overtime;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-01 09:00', 'Asia/Jakarta'));
});

it('scopes the supervisor dashboard to the saved department on each request', function () {
    $department = Department::factory()->create();
    $other = Department::factory()->create();
    $supervisor = User::factory()->supervisor($department)->create();
    $employee = Employee::factory()->for($other)->create();

    $historical = Overtime::factory()->for($employee)->for($department)->create([
        'overtime_date' => '2026-10-30',
        'start_at' => '2026-10-30 17:00:00',
        'end_at' => '2026-10-30 20:00:00',
        'exceeds_daily_limit' => true,
        'exceeds_weekly_limit' => true,
    ]);
    Overtime::factory()->for($employee)->create(['exceeds_daily_limit' => true]);
    Overtime::factory()->for($department)->create(['status' => OvertimeStatus::REJECTED, 'exceeds_daily_limit' => true]);

    $this->actingAs($supervisor)->get('/panel?department_id='.$other->id)
        ->assertInertia(fn (Assert $page) => $page
            ->component('panel/dashboard')
            ->where('kpis', [
                'pendingCount' => 1,
                'approvedCount' => 0,
                'approvedMinutes' => 0,
                'overLimitCount' => 1,
            ])
            ->has('recentPending', 1)
            ->where('recentPending.0.id', $historical->id)
            ->where('recentPending.0.employee.id', $employee->id)
            ->where('recentPending.0.department.id', $department->id)
            ->missing('recentPending.0.submitted_ip')
            ->missing('recentPending.0.submitted_user_agent'));
});

it('counts approved minutes in both departments by the calendar month of the start date', function () {
    $admin = User::factory()->admin()->create();
    $first = Department::factory()->create();
    $second = Department::factory()->create();

    foreach ([
        [$first, '2026-11-01', '2026-11-01 05:00:00', '2026-11-01 07:30:00', 150, OvertimeStatus::APPROVED],
        [$second, '2026-11-01', '2026-11-01 03:00:00', '2026-11-01 04:15:00', 75, OvertimeStatus::APPROVED],
        [$first, '2026-10-31', '2026-10-31 23:00:00', '2026-11-01 02:00:00', 180, OvertimeStatus::APPROVED],
        [$first, '2026-11-01', '2026-11-01 01:00:00', '2026-11-01 02:30:00', 90, OvertimeStatus::PENDING],
    ] as [$department, $date, $start, $end, $minutes, $status]) {
        Overtime::factory()->for($department)->create([
            'overtime_date' => $date,
            'start_at' => $start,
            'end_at' => $end,
            'total_minutes' => $minutes,
            'status' => $status,
            'approved_at' => $status === OvertimeStatus::APPROVED ? '2026-11-01 08:00:00' : null,
        ]);
    }

    $this->actingAs($admin)->get('/panel')
        ->assertInertia(fn (Assert $page) => $page
            ->component('panel/dashboard')
            ->where('monthLabel', 'November 2026')
            ->where('kpis.approvedCount', 2)
            ->where('kpis.approvedMinutes', 225)
            ->where('kpis.pendingCount', 1)
            ->has('recentPending', 1));
});

it('shows at most five pending entries ordered by overtime date and start time', function () {
    $department = Department::factory()->create();
    $supervisor = User::factory()->supervisor($department)->create();

    $ids = [];
    foreach (range(1, 6) as $hour) {
        $ids[] = Overtime::factory()->for($department)->create([
            'overtime_date' => '2026-11-01',
            'start_at' => sprintf('2026-11-01 %02d:00:00', $hour),
            'end_at' => sprintf('2026-11-01 %02d:30:00', $hour),
            'total_minutes' => 30,
        ])->id;
    }

    $this->actingAs($supervisor)->get('/panel')
        ->assertInertia(fn (Assert $page) => $page
            ->where('kpis.pendingCount', 6)
            ->has('recentPending', 5)
            ->where('recentPending.0.id', $ids[5])
            ->where('recentPending.4.id', $ids[1]));
});

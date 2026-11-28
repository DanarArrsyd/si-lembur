<?php

declare(strict_types=1);

use App\Enums\OvertimeSource;
use App\Enums\OvertimeStatus;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Overtime;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-28 10:20', 'Asia/Jakarta'));
    $this->department = Department::factory()->create();
    $this->supervisor = User::factory()->supervisor($this->department)->create();
    $this->employee = Employee::factory()->for($this->department)->create();
    $this->entry = [
        'employee_id' => $this->employee->id,
        'overtime_date' => '2026-10-01',
        'start_time' => '23:00',
        'end_time' => '02:00',
        'break_minutes' => 30,
        'description' => 'Menyelesaikan pesanan produksi',
    ];
});

it('shows only the assigned department and records proxy entry as approved with audit', function () {
    Employee::factory()->create();
    Employee::factory()->for($this->department)->create(['is_active' => false]);

    $this->actingAs($this->supervisor)->get('/panel/overtimes/create')->assertInertia(fn (Assert $page) => $page
        ->component('panel/overtimes/form')
        ->where('fixedDepartmentId', $this->department->id)
        ->where('dateBounds.min', '2026-10-01')
        ->where('dateBounds.max', '2026-11-28')
        ->has('departments', 1)
        ->has('departments.0.employees', 1)
        ->where('departments.0.employees.0.id', $this->employee->id));

    $this->post('/panel/overtimes', $this->entry)
        ->assertRedirect('/panel/overtimes?month=2026-10&status=APPROVED');

    $overtime = Overtime::query()->sole();
    expect($overtime)
        ->status->toBe(OvertimeStatus::APPROVED)
        ->source->toBe(OvertimeSource::SUPERVISOR)
        ->approved_by->toBe($this->supervisor->id)
        ->created_by->toBe($this->supervisor->id)
        ->department_id->toBe($this->department->id)
        ->total_minutes->toBe(150)
        ->and($overtime->end_at->toDateTimeString())->toBe('2026-10-02 02:00:00');

    $this->assertDatabaseHas('audit_logs', ['action' => 'overtime.created_by_supervisor', 'user_id' => $this->supervisor->id]);
});

it('rejects dates outside the panel window', function (string $date) {
    $this->actingAs($this->supervisor)->from('/panel/overtimes/create')
        ->post('/panel/overtimes', [...$this->entry, 'overtime_date' => $date])
        ->assertRedirect('/panel/overtimes/create')
        ->assertSessionHasErrors('overtime_date');

    $this->assertDatabaseCount('overtimes', 0);
})->with(['2026-09-30', '2026-11-29']);

it('rejects inactive and other department employees and a foreign department selection', function () {
    $foreign = Employee::factory()->create();
    $inactive = Employee::factory()->for($this->department)->create(['is_active' => false]);
    $this->actingAs($this->supervisor)->from('/panel/overtimes/create');

    foreach ([$foreign, $inactive] as $employee) {
        $this->post('/panel/overtimes', [...$this->entry, 'employee_id' => $employee->id])
            ->assertSessionHasErrors('employee_id');
    }
    $this->post('/panel/overtimes', [...$this->entry, 'department_id' => $foreign->department_id, 'employee_id' => $foreign->id])
        ->assertForbidden();
    $this->assertDatabaseCount('overtimes', 0);
});

it('lets an admin select an active department and rejects a mismatched employee', function () {
    $other = Employee::factory()->create();
    Department::factory()->create(['is_active' => false]);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/panel/overtimes/create')->assertInertia(fn (Assert $page) => $page
        ->where('fixedDepartmentId', null)->has('departments', 2));
    $this->from('/panel/overtimes/create')->post('/panel/overtimes', $this->entry)->assertSessionHasErrors('department_id');
    $this->post('/panel/overtimes', [...$this->entry, 'department_id' => $other->department_id])->assertSessionHasErrors('employee_id');
    $this->post('/panel/overtimes', [...$this->entry, 'department_id' => $other->department_id, 'employee_id' => $other->id])
        ->assertSessionHasNoErrors();
    expect(Overtime::query()->sole()->department_id)->toBe($other->department_id);
});

it('checks snapshot department before GET and PATCH even when the employee moved in', function () {
    $overtime = Overtime::factory()->create();
    $overtime->employee->update(['department_id' => $this->department->id]);
    $this->actingAs($this->supervisor)->get("/panel/overtimes/{$overtime->id}/edit")->assertForbidden();
    $this->patch("/panel/overtimes/{$overtime->id}", [])->assertForbidden();
});

it('shows the historical identity and ignores identity fields sent during an edit', function () {
    $other = Employee::factory()->create();
    $overtime = Overtime::factory()->for($this->employee)->create();
    $this->employee->update(['department_id' => $other->department_id, 'is_active' => false]);

    $this->actingAs($this->supervisor)->get("/panel/overtimes/{$overtime->id}/edit")->assertInertia(fn (Assert $page) => $page
        ->component('panel/overtimes/form')
        ->where('overtime.employee.name', $this->employee->name)
        ->where('overtime.department', $this->department->name)
        ->where('overtime.start_time', '17:00')
        ->missing('departments'));

    $this->patch("/panel/overtimes/{$overtime->id}", [
        ...$this->entry,
        'employee_id' => $other->id,
        'department_id' => $other->department_id,
        'status' => 'APPROVED',
        'description' => 'Koreksi kegiatan produksi',
    ])->assertRedirect('/panel/overtimes?month=2026-10&status=PENDING');

    expect($overtime->refresh())
        ->employee_id->toBe($this->employee->id)
        ->department_id->toBe($this->department->id)
        ->status->toBe(OvertimeStatus::PENDING)
        ->description->toBe('Koreksi kegiatan produksi');
    $this->assertDatabaseHas('audit_logs', ['action' => 'overtime.updated', 'user_id' => $this->supervisor->id]);
});

it('handles an edit form that became stale after approval', function () {
    $overtime = Overtime::factory()->for($this->employee)->create();
    $edit = "/panel/overtimes/{$overtime->id}/edit";
    $this->actingAs($this->supervisor)->get($edit)->assertOk();
    workflow()->approve($this->supervisor, $overtime);
    $this->get($edit)->assertRedirect('/panel/overtimes?month=2026-11&status=APPROVED');
    $this->from($edit)->patch("/panel/overtimes/{$overtime->id}", $this->entry)
        ->assertRedirect('/panel/overtimes?month=2026-11&status=APPROVED');

    expect($overtime->fresh()->status)->toBe(OvertimeStatus::APPROVED);
    $this->assertDatabaseMissing('audit_logs', ['action' => 'overtime.updated']);
});

it('checks overlap and refreshes flags for both weeks on edit', function () {
    $records = collect(['09', '10', '11', '12', '13', '14', '16', '17', '18', '19', '20', '21'])
        ->mapWithKeys(function (string $day) {
            $date = "2026-11-{$day}";

            return [$day => workflow()->submitBySupervisor($this->supervisor, overtimeInput($this->employee, [
                'date' => CarbonImmutable::parse($date),
                'dateChoice' => null,
            ]), CarbonImmutable::now())];
        });
    $moving = workflow()->submitPublic(overtimeInput($this->employee, [
        'startTime' => '06:00',
        'endTime' => '07:00',
    ]), CarbonImmutable::parse('2026-11-14 12:00'));
    expect($records['14']->fresh()->exceeds_weekly_limit)->toBeTrue()
        ->and($records['21']->fresh()->exceeds_weekly_limit)->toBeFalse();

    $input = [...$this->entry, 'overtime_date' => '2026-11-21', 'start_time' => '17:15', 'end_time' => '18:00', 'break_minutes' => 0];
    $this->actingAs($this->supervisor)->from("/panel/overtimes/{$moving->id}/edit")
        ->patch("/panel/overtimes/{$moving->id}", $input)->assertSessionHasErrors('start_time');

    $this->patch("/panel/overtimes/{$moving->id}", [...$input, 'start_time' => '06:00', 'end_time' => '07:00'])
        ->assertSessionHasNoErrors();

    expect($records['14']->fresh()->exceeds_weekly_limit)->toBeFalse()
        ->and($records['21']->fresh()->exceeds_weekly_limit)->toBeTrue();
});

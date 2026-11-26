<?php

declare(strict_types=1);

use App\Models\Department;
use App\Models\Employee;
use App\Models\Overtime;
use App\Models\User;

it('limits supervisors to overtimes of their own department', function (string $ability) {
    [$a, $b] = Department::factory()->count(2)->create();
    $supervisor = User::factory()->supervisor($a)->create();
    $own = Overtime::factory()->for(Employee::factory()->for($a))->create();
    $other = Overtime::factory()->for(Employee::factory()->for($b))->create();

    expect($supervisor->can($ability, $own))->toBeTrue()
        ->and($supervisor->can($ability, $other))->toBeFalse();
})->with(['view', 'update', 'approve', 'reject', 'cancelApproval']);

it('uses the overtime department snapshot, not the current employee department', function () {
    [$a, $b] = Department::factory()->count(2)->create();
    $supervisorA = User::factory()->supervisor($a)->create();
    $overtime = Overtime::factory()->for(Employee::factory()->for($a))->create();

    $overtime->employee->update(['department_id' => $b->id]);

    expect($supervisorA->can('approve', $overtime->fresh()))->toBeTrue();
});

it('lets admins act on overtimes of every department', function () {
    $overtime = Overtime::factory()->create();

    expect(User::factory()->admin()->create()->can('approve', $overtime))->toBeTrue();
});

it('denies inactive accounts', function () {
    $department = Department::factory()->create();
    $overtime = Overtime::factory()->for(Employee::factory()->for($department))->create();

    expect(User::factory()->admin()->create(['is_active' => false])->can('approve', $overtime))->toBeFalse();
});

it('restricts employee master changes to admins', function () {
    $department = Department::factory()->create();
    $supervisor = User::factory()->supervisor($department)->create();
    $admin = User::factory()->admin()->create();
    $employee = Employee::factory()->for($department)->create();

    expect($supervisor->can('viewAny', Employee::class))->toBeTrue()
        ->and($supervisor->can('create', Employee::class))->toBeFalse()
        ->and($supervisor->can('update', $employee))->toBeFalse()
        ->and($supervisor->can('deactivate', $employee))->toBeFalse()
        ->and($admin->can('create', Employee::class))->toBeTrue()
        ->and($admin->can('update', $employee))->toBeTrue()
        ->and($admin->can('deactivate', $employee))->toBeTrue();
});

it('exposes department access helpers on the user', function () {
    [$a, $b] = Department::factory()->count(2)->create();
    $supervisor = User::factory()->supervisor($a)->create();

    expect($supervisor->isSupervisor())->toBeTrue()
        ->and($supervisor->isAdmin())->toBeFalse()
        ->and($supervisor->canAccessDepartment($a->id))->toBeTrue()
        ->and($supervisor->canAccessDepartment($b->id))->toBeFalse()
        ->and(User::factory()->admin()->create()->canAccessDepartment($b->id))->toBeTrue();
});

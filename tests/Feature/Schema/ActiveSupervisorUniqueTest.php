<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\QueryException;

it('rejects a second active supervisor in one department', function () {
    $department = Department::factory()->create();
    User::factory()->supervisor($department)->create();

    expect(fn () => User::factory()->supervisor($department)->create())
        ->toThrow(QueryException::class);
});

it('allows an inactive second supervisor but blocks reactivating it', function () {
    $department = Department::factory()->create();
    User::factory()->supervisor($department)->create();
    $second = User::factory()->supervisor($department)->create(['is_active' => false]);

    expect(fn () => $second->update(['is_active' => true]))
        ->toThrow(QueryException::class);
});

it('blocks turning an admin into a second supervisor of the same department', function () {
    $department = Department::factory()->create();
    User::factory()->supervisor($department)->create();
    $admin = User::factory()->admin()->create(['department_id' => $department->id]);

    expect(fn () => $admin->update(['role' => UserRole::SUPERVISOR]))
        ->toThrow(QueryException::class);
});

it('allows one active supervisor in each of several departments', function () {
    [$a, $b] = Department::factory()->count(2)->create();
    User::factory()->supervisor($a)->create();
    User::factory()->supervisor($b)->create();

    expect(User::query()->where('role', UserRole::SUPERVISOR)->count())->toBe(2);
});

it('allows many admins with or without a department', function () {
    $department = Department::factory()->create();
    User::factory()->admin()->count(2)->create(['department_id' => $department->id]);
    User::factory()->admin()->count(2)->create();

    expect(User::query()->count())->toBe(4);
});

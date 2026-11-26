<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(fn () => $this->seed());

it('seeds the sample master data', function () {
    expect(Department::query()->count())->toBe(3)
        ->and(Employee::query()->count())->toBe(20)
        ->and(Holiday::query()->count())->toBeGreaterThanOrEqual(1)
        ->and(Employee::query()->where('name', 'Budi Santoso')->exists())->toBeTrue();

    Department::query()->each(
        fn (Department $d) => expect($d->employees()->count())->toBeGreaterThan(0)
    );
});

it('seeds one admin and two supervisors in different departments', function () {
    expect(User::query()->where('role', UserRole::ADMIN)->count())->toBe(1);

    $supervisors = User::query()->where('role', UserRole::SUPERVISOR)->get();

    expect($supervisors)->toHaveCount(2)
        ->and($supervisors->pluck('department_id')->unique())->toHaveCount(2)
        ->and(User::query()->where('username', 'admin')->value('department_id'))->toBeNull();
});

it('seeds default settings', function (string $key, mixed $value) {
    expect(Setting::query()->find($key)?->value)->toBe($value);
})->with([
    ['weekly_rest_days', [7]],
    ['yesterday_cutoff_time', '12:00'],
    ['daily_limit_hours', 4],
    ['weekly_limit_hours', 18],
    ['max_gross_hours', 16],
]);

it('uses the development password outside production', function () {
    expect(Hash::check('password', User::query()->where('username', 'admin')->value('password')))->toBeTrue();
});

it('is idempotent', function () {
    $this->seed();

    expect(Department::query()->count())->toBe(3)
        ->and(Employee::query()->count())->toBe(20)
        ->and(User::query()->count())->toBe(3);
});

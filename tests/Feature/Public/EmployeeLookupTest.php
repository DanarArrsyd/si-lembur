<?php

declare(strict_types=1);

use App\Models\Department;
use App\Models\Employee;

it('lists only active employees in the requested department with public fields', function () {
    $department = Department::factory()->create();
    $zaki = Employee::factory()->for($department)->create(['name' => 'Zaki']);
    $andi = Employee::factory()->for($department)->create(['name' => 'Andi', 'nik' => '00123456']);
    Employee::factory()->for($department)->create(['is_active' => false]);
    Employee::factory()->create();

    $this->getJson("/api/departments/{$department->id}/employees")
        ->assertOk()
        ->assertExactJson([
            ['id' => $andi->id, 'nik' => '00123456', 'name' => 'Andi'],
            ['id' => $zaki->id, 'nik' => $zaki->nik, 'name' => 'Zaki'],
        ]);
});

it('returns an empty list for an active department without employees', function () {
    $department = Department::factory()->create();

    $this->getJson("/api/departments/{$department->id}/employees")
        ->assertOk()
        ->assertExactJson([]);
});

it('returns 404 for inactive or missing departments', function () {
    $inactive = Department::factory()->create(['is_active' => false]);
    $active = Department::factory()->create();
    Employee::factory()->for($inactive)->create();

    $this->getJson("/api/departments/{$inactive->id}/employees")->assertNotFound();
    $this->getJson('/api/departments/0/employees')->assertNotFound();
    $this->getJson("/api/departments/{$active->id}abc/employees")->assertNotFound();
});

it('limits lookup to 60 requests per minute per client IP across departments', function () {
    $department = Department::factory()->create();
    $otherDepartment = Department::factory()->create();
    $this->freezeTime();
    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10']);

    for ($request = 1; $request <= 60; $request++) {
        $this->getJson("/api/departments/{$department->id}/employees")->assertOk();
    }

    $this->getJson("/api/departments/{$otherDepartment->id}/employees")->assertTooManyRequests();

    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.11'])
        ->getJson("/api/departments/{$department->id}/employees")
        ->assertOk();

    $this->travel(1)->minute();

    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
        ->getJson("/api/departments/{$department->id}/employees")
        ->assertOk();
});

<?php

declare(strict_types=1);

use App\Models\Department;
use App\Models\Employee;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->now = CarbonImmutable::parse('2026-11-10 20:00');
    $this->employee = Employee::factory()->create();
});

it('validates the description length after trimming (R8)', function (string $description, ?string $message) {
    $submit = fn () => workflow()->submitPublic(overtimeInput($this->employee, ['description' => $description]), $this->now);

    if ($message === null) {
        expect($submit()->description)->toBe(trim($description));
    } else {
        expect(validationErrors($submit))->toBe(['description' => [$message]]);
    }
})->with([
    'spasi saja' => ['      ', 'Tulis kegiatan lembur minimal 5 huruf.'],
    'dua huruf berspasi' => ['  ab  ', 'Tulis kegiatan lembur minimal 5 huruf.'],
    'tepat 5 huruf' => [' Kemas ', null],
    '500 huruf' => [str_repeat('a', 500), null],
    '501 huruf' => [str_repeat('a', 501), 'Kegiatan lembur maksimal 500 huruf.'],
]);

it('rejects employees not active in the chosen department (R5)', function (Closure $mutate) {
    $chosenDepartment = $this->employee->department_id;
    $mutate($this->employee);

    expect(validationErrors(fn () => workflow()->submitPublic(overtimeInput($this->employee->fresh(), [
        'departmentId' => $chosenDepartment,
    ]), $this->now)))->toBe(['employee_id' => ['Nama tidak ditemukan di departemen ini.']]);
})->with([
    'nonaktif' => [fn (Employee $e) => $e->update(['is_active' => false])],
    'pindah departemen' => [fn (Employee $e) => $e->update(['department_id' => Department::factory()->create()->id])],
    'departemen nonaktif' => [fn (Employee $e) => $e->department->update(['is_active' => false])],
]);

it('rejects an unknown employee id (R5)', function () {
    expect(validationErrors(fn () => workflow()->submitPublic(overtimeInput($this->employee, ['employeeId' => 999999]), $this->now)))
        ->toBe(['employee_id' => ['Nama tidak ditemukan di departemen ini.']]);
});

it('rejects invalid hours through the calculator (R1-R4)', function () {
    expect(validationErrors(fn () => workflow()->submitPublic(overtimeInput($this->employee, ['endTime' => '17:00']), $this->now)))
        ->toHaveKey('end_time');
});

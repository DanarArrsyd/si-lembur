<?php

declare(strict_types=1);

use App\Enums\DateChoice;
use App\Models\Employee;
use App\Services\OvertimeInput;
use App\Services\OvertimeWorkflow;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

// Test paralel: beberapa proses PHP terpisah harus melihat data yang sudah di-commit,
// jadi tidak boleh dibungkus transaksi RefreshDatabase. Hanya jalan di MySQL/MariaDB.
pest()->extend(TestCase::class)
    ->use(DatabaseTruncation::class)
    ->beforeEach(function () {
        if (! in_array(config('database.default'), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Test paralel butuh MySQL/MariaDB.');
        }
    })
    ->in('Concurrency');

/**
 * Input form lembur untuk test workflow. Default: karyawan $employee, hari ini, 17:00–20:00.
 *
 * @param  array<string, mixed>  $overrides
 */
function overtimeInput(Employee $employee, array $overrides = []): OvertimeInput
{
    return new OvertimeInput(...[
        'employeeId' => $employee->id,
        'departmentId' => $employee->department_id,
        'dateChoice' => DateChoice::TODAY,
        'date' => null,
        'startTime' => '17:00',
        'endTime' => '20:00',
        'breakMinutes' => 0,
        'description' => 'Menyelesaikan pesanan produksi',
        ...$overrides,
    ]);
}

function workflow(): OvertimeWorkflow
{
    return app(OvertimeWorkflow::class);
}

/**
 * Menjalankan $callback dan mengembalikan error ValidationException-nya.
 *
 * @return array<string, list<string>>
 */
function validationErrors(Closure $callback): array
{
    try {
        $callback();
    } catch (ValidationException $e) {
        return $e->errors();
    }

    throw new RuntimeException('Expected a ValidationException');
}

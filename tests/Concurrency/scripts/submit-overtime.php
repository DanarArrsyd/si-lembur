<?php

declare(strict_types=1);

/*
 * Dipanggil oleh DuplicateSubmitConcurrencyTest sebagai proses terpisah.
 * Argumen: employee_id department_id. Mencetak "ok:<nomor>" atau "error:<pesan>".
 */

use App\Enums\DateChoice;
use App\Services\OvertimeInput;
use App\Services\OvertimeWorkflow;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../../vendor/autoload.php';

$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$input = new OvertimeInput(
    employeeId: (int) $argv[1],
    departmentId: (int) $argv[2],
    dateChoice: DateChoice::TODAY,
    date: null,
    startTime: '17:00',
    endTime: '20:00',
    breakMinutes: 0,
    description: 'Dobel tap dari dua HP',
);

try {
    $overtime = $app->make(OvertimeWorkflow::class)->submitPublic($input, CarbonImmutable::parse('2026-11-10 21:00'));
    echo 'ok:'.$overtime->number.PHP_EOL;
} catch (ValidationException $e) {
    echo 'error:'.collect($e->errors())->flatten()->first().PHP_EOL;
}

<?php

declare(strict_types=1);

use App\Models\Employee;
use App\Models\Overtime;
use Illuminate\Support\Facades\Process;

it('stores only one of two simultaneous identical submissions', function () {
    $employee = Employee::factory()->create();
    $connection = 'database.connections.'.config('database.default');
    $env = [
        'APP_ENV' => 'testing',
        'DB_CONNECTION' => config('database.default'),
        'DB_HOST' => config("$connection.host"),
        'DB_PORT' => (string) config("$connection.port"),
        'DB_DATABASE' => config("$connection.database"),
        'DB_USERNAME' => config("$connection.username"),
        'DB_PASSWORD' => config("$connection.password"),
    ];

    $results = Process::concurrently(function ($pool) use ($env, $employee) {
        foreach (range(1, 4) as $_) {
            $pool->path(base_path())->env($env)->command([
                PHP_BINARY, 'tests/Concurrency/scripts/submit-overtime.php', (string) $employee->id, (string) $employee->department_id,
            ]);
        }
    });

    $lines = collect($results)
        ->each(fn ($r) => expect($r->successful())->toBeTrue($r->errorOutput()))
        ->map(fn ($r) => trim($r->output()));

    expect($lines->filter(fn ($l) => str_starts_with($l, 'ok:')))->toHaveCount(1)
        ->and($lines->filter(fn ($l) => str_contains($l, 'di jam yang sama')))->toHaveCount(3)
        ->and(Overtime::query()->count())->toBe(1);
});

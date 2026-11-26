<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Process;

it('hands out unique, gapless numbers to parallel processes', function () {
    $env = [
        'APP_ENV' => 'testing',
        'DB_CONNECTION' => config('database.default'),
        'DB_HOST' => config('database.connections.'.config('database.default').'.host'),
        'DB_PORT' => (string) config('database.connections.'.config('database.default').'.port'),
        'DB_DATABASE' => config('database.connections.'.config('database.default').'.database'),
        'DB_USERNAME' => config('database.connections.'.config('database.default').'.username'),
        'DB_PASSWORD' => config('database.connections.'.config('database.default').'.password'),
    ];

    $results = Process::concurrently(function ($pool) use ($env) {
        foreach (range(1, 5) as $_) {
            $pool->path(base_path())->env($env)->command([PHP_BINARY, 'tests/Concurrency/scripts/generate-numbers.php', '10']);
        }
    });

    $numbers = collect($results)
        ->each(fn ($r) => expect($r->successful())->toBeTrue($r->errorOutput()))
        ->flatMap(fn ($r) => array_filter(explode(PHP_EOL, trim($r->output()))))
        ->sort()
        ->values();

    expect($numbers)->toHaveCount(50)
        ->and($numbers->unique())->toHaveCount(50)
        ->and($numbers->first())->toBe('LBR-20261109-0001')
        ->and($numbers->last())->toBe('LBR-20261109-0050');
});

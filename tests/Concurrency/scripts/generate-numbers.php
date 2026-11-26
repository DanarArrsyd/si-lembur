<?php

declare(strict_types=1);

/*
 * Dipanggil oleh OvertimeNumberGeneratorConcurrencyTest sebagai proses terpisah.
 * Argumen: jumlah nomor. Mencetak satu nomor per baris.
 */

use App\Services\OvertimeNumberGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../../vendor/autoload.php';

$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$generator = $app->make(OvertimeNumberGenerator::class);
$now = CarbonImmutable::parse('2026-11-09 08:00');

for ($i = 0; $i < (int) ($argv[1] ?? 1); $i++) {
    echo DB::transaction(fn () => $generator->next($now), 3).PHP_EOL;
}

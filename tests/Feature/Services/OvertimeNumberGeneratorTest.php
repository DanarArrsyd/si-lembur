<?php

declare(strict_types=1);

use App\Services\OvertimeNumberGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

function nextNumber(string $now): string
{
    return DB::transaction(fn () => app(OvertimeNumberGenerator::class)->next(CarbonImmutable::parse($now)));
}

it('numbers per creation day starting at 0001', function () {
    expect(nextNumber('2026-11-09 08:00'))->toBe('LBR-20261109-0001')
        ->and(nextNumber('2026-11-09 23:59'))->toBe('LBR-20261109-0002')
        ->and(nextNumber('2026-11-10 00:00'))->toBe('LBR-20261110-0001');
});

it('refuses to run outside a transaction', function () {
    // RefreshDatabase membuka satu transaksi; tutup dulu untuk mensimulasikan pemanggilan tanpa transaksi.
    DB::rollBack();

    try {
        expect(fn () => app(OvertimeNumberGenerator::class)->next(CarbonImmutable::parse('2026-11-09')))
            ->toThrow(LogicException::class);
    } finally {
        DB::beginTransaction();
    }
});

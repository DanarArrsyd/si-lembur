<?php

declare(strict_types=1);

use App\Enums\DayType;
use App\Models\Holiday;
use App\Services\DayTypeResolver;
use App\Services\Settings;
use Carbon\CarbonImmutable;

function dayType(string $date): DayType
{
    return app(DayTypeResolver::class)->resolve(CarbonImmutable::parse($date));
}

it('marks the default weekly rest day as holiday (H1)', function () {
    expect(dayType('2026-11-08'))->toBe(DayType::HARI_LIBUR); // Minggu
});

it('marks dates in the holidays table as holiday (H2)', function () {
    Holiday::factory()->create(['date' => '2026-11-10']); // Selasa

    expect(dayType('2026-11-10'))->toBe(DayType::HARI_LIBUR)
        ->and(dayType('2026-11-11'))->toBe(DayType::HARI_KERJA);
});

it('treats Saturday as a work day by default', function () {
    expect(dayType('2026-11-07'))->toBe(DayType::HARI_KERJA);
});

it('follows the weekly rest days setting', function () {
    app(Settings::class)->set('weekly_rest_days', [6, 7]);

    expect(dayType('2026-11-07'))->toBe(DayType::HARI_LIBUR);
});

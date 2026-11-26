<?php

declare(strict_types=1);

use App\Models\Setting;
use App\Services\Settings;

it('falls back to defaults when no rows exist', function () {
    $settings = app(Settings::class);

    expect($settings->weeklyRestDays())->toBe([7])
        ->and($settings->yesterdayCutoff())->toBe('12:00')
        ->and($settings->dailyLimitMinutes())->toBe(240)
        ->and($settings->weeklyLimitMinutes())->toBe(1080)
        ->and($settings->maxGrossMinutes())->toBe(960);
});

it('reads stored values over defaults', function () {
    Setting::query()->create(['key' => 'weekly_rest_days', 'value' => [6, 7]]);
    Setting::query()->create(['key' => 'daily_limit_hours', 'value' => 3]);

    $settings = app(Settings::class);

    expect($settings->weeklyRestDays())->toBe([6, 7])
        ->and($settings->dailyLimitMinutes())->toBe(180);
});

it('sees a new value right after set', function () {
    $settings = app(Settings::class);
    expect($settings->yesterdayCutoff())->toBe('12:00');

    $settings->set('yesterday_cutoff_time', '10:00');

    expect(app(Settings::class)->yesterdayCutoff())->toBe('10:00')
        ->and(Setting::query()->find('yesterday_cutoff_time')->value)->toBe('10:00');
});

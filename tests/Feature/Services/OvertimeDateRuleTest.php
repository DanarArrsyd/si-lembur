<?php

declare(strict_types=1);

use App\Enums\DateChoice;
use App\Services\OvertimeDateRule;
use App\Services\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

function dateRule(): OvertimeDateRule
{
    return app(OvertimeDateRule::class);
}

function at(string $datetime): CarbonImmutable
{
    return CarbonImmutable::parse($datetime, 'Asia/Jakarta');
}

// BUSINESS_RULES.md §7.2
it('accepts yesterday before the cutoff (D1, D2)', function (string $now) {
    expect(dateRule()->resolvePublic(DateChoice::YESTERDAY, at($now))->toDateString())->toBe('2026-11-09');
})->with(['D1' => '2026-11-10 02:30', 'D2' => '2026-11-10 11:59:59']);

it('rejects yesterday at or after the cutoff (D3)', function () {
    expect(fn () => dateRule()->resolvePublic(DateChoice::YESTERDAY, at('2026-11-10 12:00')))
        ->toThrow(function (ValidationException $e) {
            expect($e->errors())->toBe(['overtime_date' => [
                'Batas input lembur kemarin sudah lewat (12:00). Hubungi supervisor untuk input lembur kemarin.',
            ]]);
        });
});

it('always accepts today', function () {
    expect(dateRule()->resolvePublic(DateChoice::TODAY, at('2026-11-10 23:59'))->toDateString())->toBe('2026-11-10');
});

it('offers only today and yesterday, never a future date (D4)', function () {
    $choices = dateRule()->publicChoices(at('2026-11-10 09:00'));

    expect(array_map(fn ($c) => [$c['choice'], $c['date']->toDateString()], $choices))->toBe([
        [DateChoice::TODAY, '2026-11-10'],
        [DateChoice::YESTERDAY, '2026-11-09'],
    ]);
});

it('drops yesterday from the choices after the cutoff', function () {
    expect(dateRule()->publicChoices(at('2026-11-10 12:00')))->toHaveCount(1);
});

it('follows a changed cutoff setting', function () {
    app(Settings::class)->set('yesterday_cutoff_time', '10:00');

    expect(fn () => dateRule()->resolvePublic(DateChoice::YESTERDAY, at('2026-11-10 10:00')))
        ->toThrow(ValidationException::class, 'Batas input lembur kemarin sudah lewat (10:00).');
});

it('accepts panel dates in this or the previous month (D5)', function (string $now, string $date) {
    dateRule()->assertPanelDate(at($date), at($now));
    expect(true)->toBeTrue();
})->with([
    'D5' => ['2026-12-05 09:00', '2026-11-15'],
    'first of previous month' => ['2026-12-05 09:00', '2026-11-01'],
    'today' => ['2026-12-05 09:00', '2026-12-05'],
    'across the year' => ['2027-01-05 09:00', '2026-12-15'],
]);

it('rejects panel dates older than the previous month (D6)', function () {
    expect(fn () => dateRule()->assertPanelDate(at('2026-10-20'), at('2026-12-05 09:00')))
        ->toThrow(ValidationException::class, 'Tanggal lembur hanya bisa di bulan ini atau bulan sebelumnya.');
});

it('rejects future panel dates (D4)', function () {
    expect(fn () => dateRule()->assertPanelDate(at('2026-11-11'), at('2026-11-10 09:00')))
        ->toThrow(ValidationException::class, 'Tanggal lembur tidak boleh melewati hari ini.');
});

<?php

declare(strict_types=1);

use App\Services\OvertimeCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

function calculate(string $start, string $end, int $break, string $date = '2026-11-09')
{
    return app(OvertimeCalculator::class)->calculate(CarbonImmutable::parse($date), $start, $end, $break);
}

/**
 * @return array<string, list<string>>
 */
function calculationErrors(string $start, string $end, int $break): array
{
    try {
        calculate($start, $end, $break);
    } catch (ValidationException $e) {
        return $e->errors();
    }

    throw new RuntimeException('Expected a ValidationException');
}

// BUSINESS_RULES.md §7.1
it('calculates valid cases C1-C3', function (string $start, string $end, int $break, int $total, string $startAt, string $endAt) {
    $times = calculate($start, $end, $break);

    expect($times->totalMinutes)->toBe($total)
        ->and($times->breakMinutes)->toBe($break)
        ->and($times->grossMinutes)->toBe($total + $break)
        ->and($times->startAt->format('Y-m-d H:i'))->toBe($startAt)
        ->and($times->endAt->format('Y-m-d H:i'))->toBe($endAt);
})->with([
    'C1 lewat tengah malam' => ['23:00', '02:00', 30, 150, '2026-11-09 23:00', '2026-11-10 02:00'],
    'C2' => ['17:00', '20:00', 0, 180, '2026-11-09 17:00', '2026-11-09 20:00'],
    'C3' => ['05:30', '07:45', 15, 120, '2026-11-09 05:30', '2026-11-09 07:45'],
]);

it('rejects invalid cases C4-C7 with the documented message', function (string $start, string $end, int $break, string $field, string $message) {
    expect(calculationErrors($start, $end, $break))->toBe([$field => [$message]]);
})->with([
    'C4 R3' => ['19:45', '20:15', 30, 'break_minutes', 'Istirahat harus lebih singkat dari lama lembur.'],
    'C5 R2' => ['08:00', '08:00', 0, 'end_time', 'Jam selesai tidak boleh sama dengan jam mulai.'],
    'C6 R1' => ['08:10', '10:00', 0, 'start_time', 'Jam harus kelipatan 15 menit.'],
    'C7 R4' => ['06:00', '23:00', 0, 'end_time', 'Lama lembur melebihi batas wajar. Periksa jam mulai dan jam selesai.'],
]);

it('applies R1 to end time and break', function (string $start, string $end, int $break, string $field) {
    expect(calculationErrors($start, $end, $break))->toHaveKey($field);
})->with([
    ['08:00', '10:05', 0, 'end_time'],
    ['08:00', '10:00', 10, 'break_minutes'],
]);

it('rejects malformed times as R1', function (string $start) {
    expect(calculationErrors($start, '10:00', 0))->toBe(['start_time' => ['Jam harus kelipatan 15 menit.']]);
})->with(['25:00', '8:00', 'abc', '']);

it('limits break to 4 hours', function () {
    expect(calculationErrors('08:00', '20:00', 255))->toBe(['break_minutes' => ['Istirahat maksimal 4 jam.']]);
});

it('accepts exactly 16 hours gross', function () {
    expect(calculate('06:00', '22:00', 0)->grossMinutes)->toBe(960);
});

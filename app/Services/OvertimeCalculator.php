<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Perhitungan jam lembur (BUSINESS_RULES.md §2) dan validasi R1–R4.
 *
 * Lembur selalu dicatat pada tanggal mulai. Bila jam selesai <= jam mulai,
 * lembur dianggap lewat tengah malam dan selesai di hari berikutnya.
 */
class OvertimeCalculator
{
    public const STEP_MINUTES = 15;

    public const MAX_BREAK_MINUTES = 240;

    public function __construct(private readonly Settings $settings) {}

    /**
     * @param  string  $start  Jam mulai "HH:MM"
     * @param  string  $end  Jam selesai "HH:MM"
     *
     * @throws ValidationException
     */
    public function calculate(CarbonImmutable $date, string $start, string $end, int $breakMinutes): OvertimeTimes
    {
        $startMinute = $this->parseTime($start, 'start_time');
        $endMinute = $this->parseTime($end, 'end_time');

        if ($breakMinutes < 0 || $breakMinutes % self::STEP_MINUTES !== 0) {
            throw $this->fail('break_minutes', 'Jam harus kelipatan 15 menit.');
        }

        if ($breakMinutes > self::MAX_BREAK_MINUTES) {
            throw $this->fail('break_minutes', 'Istirahat maksimal 4 jam.');
        }

        // R2
        if ($startMinute === $endMinute) {
            throw $this->fail('end_time', 'Jam selesai tidak boleh sama dengan jam mulai.');
        }

        $day = $date->startOfDay();
        $startAt = $day->addMinutes($startMinute);
        $endAt = $day->addMinutes($endMinute);

        if ($endMinute <= $startMinute) {
            $endAt = $endAt->addDay();
        }

        $grossMinutes = (int) $startAt->diffInMinutes($endAt);

        // R4
        if ($grossMinutes > $this->settings->maxGrossMinutes()) {
            throw $this->fail('end_time', 'Lama lembur melebihi batas wajar. Periksa jam mulai dan jam selesai.');
        }

        // R3
        if ($breakMinutes >= $grossMinutes) {
            throw $this->fail('break_minutes', 'Istirahat harus lebih singkat dari lama lembur.');
        }

        return new OvertimeTimes($startAt, $endAt, $grossMinutes, $breakMinutes, $grossMinutes - $breakMinutes);
    }

    /**
     * Mengubah "HH:MM" menjadi menit sejak 00:00. Format salah diperlakukan sebagai pelanggaran R1.
     */
    private function parseTime(string $value, string $field): int
    {
        if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $value, $m) !== 1) {
            throw $this->fail($field, 'Jam harus kelipatan 15 menit.');
        }

        $minutes = ((int) $m[1]) * 60 + (int) $m[2];

        // R1
        if ($minutes % self::STEP_MINUTES !== 0) {
            throw $this->fail($field, 'Jam harus kelipatan 15 menit.');
        }

        return $minutes;
    }

    private function fail(string $field, string $message): ValidationException
    {
        return ValidationException::withMessages([$field => $message]);
    }
}

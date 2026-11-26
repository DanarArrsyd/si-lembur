<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Akses bertipe ke tabel settings. Semua baris di-cache sebagai satu entri;
 * key yang belum tersimpan memakai DEFAULTS.
 */
class Settings
{
    private const CACHE_KEY = 'settings.all';

    /**
     * Nilai default (BUSINESS_RULES.md §1, §2.1 R4, §3, §4).
     * weekly_rest_days memakai nomor hari ISO-8601: 1 = Senin ... 7 = Minggu.
     *
     * @var array<string, mixed>
     */
    public const DEFAULTS = [
        'weekly_rest_days' => [7],
        'yesterday_cutoff_time' => '12:00',
        'daily_limit_hours' => 4,
        'weekly_limit_hours' => 18,
        'max_gross_hours' => 16,
    ];

    /**
     * @return list<int>
     */
    public function weeklyRestDays(): array
    {
        $days = $this->get('weekly_rest_days');

        return array_values(array_map('intval', is_array($days) ? $days : []));
    }

    /** Jam batas pilihan "Kemarin", format "H:i". */
    public function yesterdayCutoff(): string
    {
        return (string) $this->get('yesterday_cutoff_time');
    }

    public function dailyLimitMinutes(): int
    {
        return $this->hoursToMinutes('daily_limit_hours');
    }

    public function weeklyLimitMinutes(): int
    {
        return $this->hoursToMinutes('weekly_limit_hours');
    }

    public function maxGrossMinutes(): int
    {
        return $this->hoursToMinutes('max_gross_hours');
    }

    public function set(string $key, mixed $value): void
    {
        Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget(self::CACHE_KEY);
    }

    private function get(string $key): mixed
    {
        /** @var array<string, mixed> $stored */
        $stored = Cache::rememberForever(
            self::CACHE_KEY,
            fn (): array => Setting::query()->pluck('value', 'key')->all(),
        );

        return $stored[$key] ?? self::DEFAULTS[$key] ?? null;
    }

    private function hoursToMinutes(string $key): int
    {
        $hours = $this->get($key);

        // Jam bisa pecahan (mis. 3.5) dari pengaturan admin; dibulatkan ke menit.
        return (int) round((is_numeric($hours) ? (float) $hours : 0.0) * 60);
    }
}

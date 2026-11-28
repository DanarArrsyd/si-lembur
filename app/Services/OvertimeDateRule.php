<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DateChoice;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Aturan tanggal lembur (BUSINESS_RULES.md §1, R7). $now selalu jam server Asia/Jakarta.
 */
class OvertimeDateRule
{
    public function __construct(private readonly Settings $settings) {}

    /**
     * Pilihan tanggal untuk form publik: "Hari ini", dan "Kemarin" hanya sebelum jam batas.
     *
     * @return list<array{choice: DateChoice, date: CarbonImmutable}>
     */
    public function publicChoices(CarbonImmutable $now): array
    {
        $choices = [['choice' => DateChoice::TODAY, 'date' => $now->startOfDay()]];

        if ($this->beforeCutoff($now)) {
            $choices[] = ['choice' => DateChoice::YESTERDAY, 'date' => $now->subDay()->startOfDay()];
        }

        return $choices;
    }

    /**
     * Mengubah pilihan form publik menjadi tanggal. Validasi ulang di server saat submit,
     * karena form bisa dibuka sebelum jam batas lalu dikirim sesudahnya.
     *
     * @throws ValidationException
     */
    public function resolvePublic(DateChoice $choice, CarbonImmutable $now): CarbonImmutable
    {
        if ($choice === DateChoice::YESTERDAY && ! $this->beforeCutoff($now)) {
            throw ValidationException::withMessages([
                'overtime_date' => sprintf(
                    'Batas input lembur kemarin sudah lewat (%s). Hubungi supervisor untuk input lembur kemarin.',
                    $this->settings->yesterdayCutoff(),
                ),
            ]);
        }

        return $choice === DateChoice::TODAY ? $now->startOfDay() : $now->subDay()->startOfDay();
    }

    /** @return array{min: CarbonImmutable, max: CarbonImmutable} */
    public function panelDateBounds(CarbonImmutable $now): array
    {
        return ['min' => $now->startOfMonth()->subMonthNoOverflow(), 'max' => $now->startOfDay()];
    }

    /**
     * Input Supervisor/Admin: tanggal mundur sampai awal bulan sebelumnya, tidak boleh maju.
     *
     * @throws ValidationException
     */
    public function assertPanelDate(CarbonImmutable $date, CarbonImmutable $now): void
    {
        $day = $date->startOfDay();
        $bounds = $this->panelDateBounds($now);

        if ($day->greaterThan($bounds['max'])) {
            throw ValidationException::withMessages(['overtime_date' => 'Tanggal lembur tidak boleh melewati hari ini.']);
        }

        if ($day->lessThan($bounds['min'])) {
            throw ValidationException::withMessages(['overtime_date' => 'Tanggal lembur hanya bisa di bulan ini atau bulan sebelumnya.']);
        }
    }

    private function beforeCutoff(CarbonImmutable $now): bool
    {
        [$hour, $minute] = array_map('intval', explode(':', $this->settings->yesterdayCutoff()));

        return $now->lessThan($now->setTime($hour, $minute));
    }
}

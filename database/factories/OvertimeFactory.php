<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DayType;
use App\Enums\OvertimeSource;
use App\Enums\OvertimeStatus;
use App\Models\Employee;
use App\Models\Overtime;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Data mentah untuk test; tidak menjalankan aturan bisnis (OvertimeCalculator dsb. di Fase 2).
 *
 * @extends Factory<Overtime>
 */
class OvertimeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'LBR-20261109-'.fake()->unique()->numerify('####'),
            'employee_id' => Employee::factory(),
            // Snapshot departemen karyawan, kecuali diisi eksplisit (mis. ->for($department)).
            'department_id' => fn (array $attributes) => Employee::query()->whereKey($attributes['employee_id'])->value('department_id'),
            'overtime_date' => '2026-11-09',
            'start_at' => '2026-11-09 17:00:00',
            'end_at' => '2026-11-09 20:00:00',
            'break_minutes' => 0,
            'total_minutes' => 180,
            'day_type' => DayType::HARI_KERJA,
            'exceeds_daily_limit' => false,
            'exceeds_weekly_limit' => false,
            'description' => 'Menyelesaikan pesanan produksi',
            'status' => OvertimeStatus::PENDING,
            'source' => OvertimeSource::PUBLIC,
        ];
    }
}

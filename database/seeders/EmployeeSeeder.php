<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Employee;
use Illuminate\Database\Seeder;

/**
 * 20 karyawan contoh. "Budi Santoso" dipakai untuk uji typeahead (PRD §8 no. 4).
 */
class EmployeeSeeder extends Seeder
{
    /**
     * @var array<string, list<string>>
     */
    private const EMPLOYEES = [
        'PRD' => [
            'Budi Santoso', 'Siti Rahmawati', 'Agus Setiawan', 'Dewi Lestari', 'Joko Prasetyo',
            'Rina Kartika', 'Hendra Wijaya', 'Sri Wahyuni',
        ],
        'QC' => [
            'Andi Pratama', 'Nur Aisyah', 'Bambang Susilo', 'Fitri Handayani', 'Eko Saputra', 'Lina Marlina',
        ],
        'WH' => [
            'Rudi Hartono', 'Wulan Sari', 'Teguh Purnomo', 'Yanti Susanti', 'Dedi Kurniawan', 'Maya Anggraini',
        ],
    ];

    public function run(): void
    {
        $sequence = 1;

        foreach (self::EMPLOYEES as $code => $names) {
            $departmentId = Department::query()->where('code', $code)->value('id');

            foreach ($names as $name) {
                Employee::query()->updateOrCreate(
                    ['nik' => sprintf('KMI%04d', $sequence++)],
                    ['name' => $name, 'department_id' => $departmentId, 'is_active' => true],
                );
            }
        }
    }
}

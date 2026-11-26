<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Data contoh untuk development. Data karyawan dan hari libur asli diimpor di Fase 5,
 * dan data contoh dihapus sebelum produksi (Fase 7).
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            SettingSeeder::class,
            DepartmentSeeder::class,
            EmployeeSeeder::class,
            UserSeeder::class,
            HolidaySeeder::class,
        ]);
    }
}

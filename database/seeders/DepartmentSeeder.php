<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * @var array<string, string>
     */
    public const DEPARTMENTS = [
        'PRD' => 'Produksi',
        'QC' => 'Quality Control',
        'WH' => 'Gudang',
    ];

    public function run(): void
    {
        foreach (self::DEPARTMENTS as $code => $name) {
            Department::query()->updateOrCreate(['code' => $code], ['name' => $name, 'is_active' => true]);
        }
    }
}

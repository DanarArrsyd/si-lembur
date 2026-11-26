<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * 1 admin + 2 supervisor (Produksi, Quality Control). Gudang sengaja tanpa supervisor.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $password = $this->password();

        User::query()->updateOrCreate(
            ['username' => 'admin'],
            ['name' => 'Admin HRD', 'password' => $password, 'role' => UserRole::ADMIN, 'department_id' => null, 'is_active' => true],
        );

        foreach (['PRD' => ['spv.produksi', 'Supervisor Produksi'], 'QC' => ['spv.qc', 'Supervisor QC']] as $code => [$username, $name]) {
            User::query()->updateOrCreate(
                ['username' => $username],
                [
                    'name' => $name,
                    'password' => $password,
                    'role' => UserRole::SUPERVISOR,
                    'department_id' => Department::query()->where('code', $code)->value('id'),
                    'is_active' => true,
                ],
            );
        }
    }

    /**
     * Fallback "password" hanya diizinkan di local/testing agar akun contoh tidak bocor ke produksi.
     */
    private function password(): string
    {
        $configured = config('seed.default_password');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        if (app()->environment(['local', 'testing'])) {
            return 'password';
        }

        throw new RuntimeException('Isi SEED_DEFAULT_PASSWORD sebelum menjalankan UserSeeder di luar local/testing.');
    }
}

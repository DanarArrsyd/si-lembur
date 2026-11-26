<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;

/**
 * Supervisor boleh melihat daftar karyawan (untuk input atas nama); perubahan master hanya Admin.
 */
class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->isAdmin();
    }

    public function update(User $user, Employee $employee): bool
    {
        return $user->is_active && $user->isAdmin();
    }

    public function deactivate(User $user, Employee $employee): bool
    {
        return $user->is_active && $user->isAdmin();
    }
}

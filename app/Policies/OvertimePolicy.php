<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Overtime;
use App\Models\User;

/**
 * Dasar Fase 1: hanya scoping departemen. Syarat status (mis. edit hanya MENUNGGU)
 * ditambahkan bersama OvertimeWorkflow di Fase 2/4.
 *
 * Departemen yang dicek adalah snapshot di pengajuan, bukan departemen karyawan saat ini.
 */
class OvertimePolicy
{
    public function view(User $user, Overtime $overtime): bool
    {
        return $user->canAccessDepartment($overtime->department_id);
    }

    public function update(User $user, Overtime $overtime): bool
    {
        return $user->canAccessDepartment($overtime->department_id);
    }

    public function approve(User $user, Overtime $overtime): bool
    {
        return $user->canAccessDepartment($overtime->department_id);
    }

    public function reject(User $user, Overtime $overtime): bool
    {
        return $user->canAccessDepartment($overtime->department_id);
    }

    public function cancelApproval(User $user, Overtime $overtime): bool
    {
        return $user->canAccessDepartment($overtime->department_id);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Models\Department;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;

class EmployeeLookupController
{
    public function __invoke(Department $department): JsonResponse
    {
        abort_unless($department->is_active, 404);

        $employees = $department->employees()
            ->where('is_active', true)
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'nik', 'name'])
            ->map(fn (Employee $employee): array => [
                'id' => $employee->id,
                'nik' => $employee->nik,
                'name' => $employee->name,
            ]);

        return response()->json($employees);
    }
}

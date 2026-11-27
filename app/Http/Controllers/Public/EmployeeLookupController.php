<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\JsonResponse;

/**
 * Daftar karyawan aktif per departemen untuk typeahead form publik (PRD §4.1).
 * Dimuat sekali per departemen; pencocokan nama dilakukan di browser.
 *
 * Hanya id, NIK, dan nama yang dikirim (ARCHITECTURE.md §5). Departemen nonaktif → 404.
 */
class EmployeeLookupController extends Controller
{
    public function __invoke(Department $department): JsonResponse
    {
        abort_unless($department->is_active, 404);

        $employees = $department->employees()
            ->where('is_active', true)
            ->orderBy('name')
            ->orderBy('nik')
            ->get(['id', 'nik', 'name']);

        return response()->json($employees);
    }
}

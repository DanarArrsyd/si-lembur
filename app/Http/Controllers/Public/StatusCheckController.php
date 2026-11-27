<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Enums\OvertimeStatus;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Overtime;
use App\Services\OvertimeDateRule;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StatusCheckController
{
    public function show(Request $request): Response
    {
        $departments = Department::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $department = $departments->find($request->integer('department', strict: true));
        $employee = $department?->employees()
            ->where('is_active', true)
            ->find($request->integer('employee', strict: true), ['id', 'nik', 'name']);

        return Inertia::render('public/status', [
            'departments' => $departments,
            'prefillDepartmentId' => $department?->id,
            'prefillEmployee' => $employee,
        ]);
    }

    public function index(Request $request, OvertimeDateRule $dates): JsonResponse
    {
        $request->validate(['employee_id' => ['required', 'integer', 'min:1']], [
            'employee_id' => 'Pilih nama dari daftar.',
        ]);

        $employee = Employee::query()->where('is_active', true)
            ->whereHas('department', fn ($query) => $query->where('is_active', true))
            ->findOrFail($request->integer('employee_id'), ['id']);

        $now = CarbonImmutable::now();
        $resubmissionDates = array_map(
            fn (array $option): string => $option['date']->toDateString(),
            $dates->publicChoices($now),
        );

        $history = $employee->overtimes()
            ->whereDate('overtime_date', '>=', $now->subDays(60)->toDateString())
            ->whereDate('overtime_date', '<=', $now->toDateString())
            ->orderByDesc('overtime_date')
            ->orderByDesc('start_at')
            ->orderByDesc('id')
            ->get(['number', 'overtime_date', 'start_at', 'end_at', 'total_minutes', 'status', 'reject_reason'])
            ->map(function (Overtime $overtime) use ($resubmissionDates): array {
                $date = $overtime->overtime_date->toDateString();
                $rejected = $overtime->status === OvertimeStatus::REJECTED;

                return [
                    'number' => $overtime->number,
                    'date' => $date,
                    'start' => $overtime->start_at->format('Y-m-d H:i'),
                    'end' => $overtime->end_at->format('Y-m-d H:i'),
                    'total_minutes' => $overtime->total_minutes,
                    'status' => $overtime->status->value,
                    'status_label' => $overtime->status->label(),
                    'reject_reason' => $rejected ? $overtime->reject_reason : null,
                    'can_resubmit' => $rejected && in_array($date, $resubmissionDates, true),
                ];
            });

        return response()->json($history, headers: ['Cache-Control' => 'private, no-store']);
    }
}

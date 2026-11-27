<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Overtime;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class PanelOvertimeQuery
{
    /** @return Builder<Overtime> */
    public function visibleTo(User $actor): Builder
    {
        $query = Overtime::query();

        if (! $actor->is_active) {
            return $query->whereKey([]);
        }

        return $actor->isAdmin()
            ? $query
            : $query->where('department_id', $actor->department_id);
    }

    /** @return array{id: int, number: string, employee: array{id: int, name: string, nik: string}, department: array{id: int, name: string}, date: string, start_at: string, end_at: string, break_minutes: int, total_minutes: int, status: string, description: string, exceeds_daily_limit: bool, exceeds_weekly_limit: bool} */
    public function row(Overtime $overtime): array
    {
        return [
            'id' => $overtime->id,
            'number' => $overtime->number,
            'employee' => [
                'id' => $overtime->employee_id,
                'name' => $overtime->employee->name,
                'nik' => $overtime->employee->nik,
            ],
            'department' => [
                'id' => $overtime->department_id,
                'name' => $overtime->department->name,
            ],
            'date' => $overtime->overtime_date->toDateString(),
            'start_at' => $overtime->start_at->format('Y-m-d H:i'),
            'end_at' => $overtime->end_at->format('Y-m-d H:i'),
            'break_minutes' => $overtime->break_minutes,
            'total_minutes' => $overtime->total_minutes,
            'status' => $overtime->status->value,
            'description' => $overtime->description,
            'exceeds_daily_limit' => $overtime->exceeds_daily_limit,
            'exceeds_weekly_limit' => $overtime->exceeds_weekly_limit,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Enums\OvertimeStatus;
use App\Exceptions\InvalidOvertimeTransition;
use App\Http\Requests\Panel\SaveOvertimeRequest;
use App\Models\Department;
use App\Models\Overtime;
use App\Models\User;
use App\Services\OvertimeDateRule;
use App\Services\OvertimeWorkflow;
use App\Services\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class OvertimeFormController
{
    public function __construct(
        private readonly OvertimeDateRule $dates,
        private readonly Settings $settings,
        private readonly OvertimeWorkflow $workflow,
    ) {}

    public function create(Request $request): Response
    {
        $actor = $this->actor($request);
        $departments = Department::query()
            ->where('is_active', true)
            ->when($actor->isSupervisor(), fn ($query) => $query->where('id', $actor->department_id))
            ->with(['employees' => fn ($query) => $query->where('is_active', true)
                ->orderBy('name')->orderBy('nik')->select('id', 'department_id', 'name', 'nik')])
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('panel/overtimes/form', [
            ...$this->formProps(),
            'fixedDepartmentId' => $actor->isSupervisor() ? $actor->department_id : null,
            'departments' => $departments,
        ]);
    }

    public function store(SaveOvertimeRequest $request): RedirectResponse
    {
        $actor = $this->actor($request);
        $overtime = $this->workflow->submitBySupervisor($actor, $request->overtimeInput($actor), CarbonImmutable::now());

        return $this->history($overtime, 'Lembur dicatat dan langsung disetujui.');
    }

    public function edit(Overtime $overtime): Response|RedirectResponse
    {
        if ($overtime->status !== OvertimeStatus::PENDING) {
            return $this->staleEdit($overtime, new InvalidOvertimeTransition($overtime->status));
        }

        return Inertia::render('panel/overtimes/form', [
            ...$this->formProps(),
            'overtime' => [
                'id' => $overtime->id,
                'number' => $overtime->number,
                'employee' => $overtime->employee->only('name', 'nik'),
                'department' => $overtime->department->name,
                'overtime_date' => $overtime->overtime_date->toDateString(),
                'start_time' => $overtime->start_at->format('H:i'),
                'end_time' => $overtime->end_at->format('H:i'),
                'break_minutes' => $overtime->break_minutes,
                'description' => $overtime->description,
            ],
        ]);
    }

    public function update(SaveOvertimeRequest $request, Overtime $overtime): RedirectResponse
    {
        $actor = $this->actor($request);

        try {
            $updated = $this->workflow->update($actor, $overtime, $request->overtimeInput($actor, $overtime), CarbonImmutable::now());
        } catch (InvalidOvertimeTransition $exception) {
            return $this->staleEdit($overtime->refresh(), $exception);
        }

        return $this->history($updated, 'Perubahan disimpan. Pengajuan masih Menunggu.');
    }

    /** @return array{dateBounds: array{min: string, max: string}, serverTime: string, maxGrossMinutes: int} */
    private function formProps(): array
    {
        $now = CarbonImmutable::now();
        $bounds = $this->dates->panelDateBounds($now);

        return [
            'dateBounds' => ['min' => $bounds['min']->toDateString(), 'max' => $bounds['max']->toDateString()],
            'serverTime' => $now->format('H:i'),
            'maxGrossMinutes' => $this->settings->maxGrossMinutes(),
        ];
    }

    private function history(Overtime $overtime, string $message, string $type = 'success'): RedirectResponse
    {
        Inertia::flash('toast', ['type' => $type, 'message' => $message]);

        return to_route('panel.overtimes.index', [
            'month' => $overtime->overtime_date->format('Y-m'),
            'status' => $overtime->status->value,
        ], 303);
    }

    private function staleEdit(Overtime $overtime, InvalidOvertimeTransition $exception): RedirectResponse
    {
        return $this->history($overtime, $exception->getMessage(), 'error');
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}

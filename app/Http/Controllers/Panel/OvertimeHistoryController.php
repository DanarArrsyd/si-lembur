<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Enums\OvertimeStatus;
use App\Exceptions\InvalidOvertimeTransition;
use App\Http\Requests\Panel\OvertimeReasonRequest;
use App\Models\Employee;
use App\Models\Overtime;
use App\Models\User;
use App\Queries\PanelOvertimeQuery;
use App\Services\OvertimeDateRule;
use App\Services\OvertimeWorkflow;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class OvertimeHistoryController
{
    public function index(Request $request, PanelOvertimeQuery $overtimes, OvertimeDateRule $dates): Response|RedirectResponse
    {
        $now = CarbonImmutable::now();
        $filters = $this->filters($request, $now);
        $month = CarbonImmutable::parse($filters['month'].'-01');
        $window = $dates->panelDateBounds($now);
        $visible = $overtimes->visibleTo($this->actor($request));

        $history = (clone $visible)
            ->whereDate('overtime_date', '>=', $month->toDateString())
            ->whereDate('overtime_date', '<', $month->addMonth()->toDateString())
            ->when($filters['status'] !== null, fn ($query) => $query->where('status', $filters['status']))
            ->when($filters['employee_id'] !== null, fn ($query) => $query->where('employee_id', $filters['employee_id']))
            ->with(['employee:id,name,nik', 'department:id,name'])
            ->orderByDesc('overtime_date')
            ->orderByDesc('start_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->appends($filters)
            ->through(fn (Overtime $overtime) => [
                ...$overtimes->row($overtime),
                'can_cancel_approval' => $overtime->status === OvertimeStatus::APPROVED
                    && $overtime->overtime_date->betweenIncluded($window['min'], $window['max']),
            ]);

        if ($history->currentPage() > $history->lastPage()) {
            $request->session()->reflash();

            return redirect()->to($history->url($history->lastPage()));
        }

        return Inertia::render('panel/overtimes/index', [
            'history' => $history,
            'filters' => $filters,
            'employees' => Employee::query()->whereIn('id', $visible->select('employee_id'))
                ->orderBy('name')->orderBy('nik')->get(['id', 'name', 'nik']),
            'cancellationWindow' => [
                'min' => $window['min']->toDateString(),
                'max' => $window['max']->toDateString(),
            ],
        ]);
    }

    public function cancelApproval(OvertimeReasonRequest $request, Overtime $overtime, OvertimeWorkflow $workflow): RedirectResponse
    {
        $now = CarbonImmutable::now();
        $params = $this->filters($request, $now);
        $page = $request->integer('page', 1, strict: true);
        if ($page > 1) {
            $params['page'] = $page;
        }

        $redirect = to_route('panel.overtimes.index', $params, 303);

        try {
            $workflow->cancelApproval($this->actor($request), $overtime, (string) $request->string('reason'), $now);
        } catch (InvalidOvertimeTransition $exception) {
            return $redirect->withErrors(['status' => $exception->getMessage()]);
        } catch (ValidationException $exception) {
            return $redirect->withErrors($exception->errors());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Persetujuan dibatalkan. Pengajuan kembali Menunggu.']);

        return $redirect;
    }

    /** @return array{month: string, status: string|null, employee_id: int|null} */
    private function filters(Request $request, CarbonImmutable $now): array
    {
        $month = $request->query('month');
        $status = $request->query('status');
        $employee = $request->integer('employee_id', strict: true);

        return [
            'month' => is_string($month) && preg_match('/\A[1-9]\d{3}-(?:0[1-9]|1[0-2])\z/', $month)
                ? $month
                : $now->format('Y-m'),
            'status' => is_string($status) ? OvertimeStatus::tryFrom($status)?->value : null,
            'employee_id' => $employee > 0 ? $employee : null,
        ];
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}

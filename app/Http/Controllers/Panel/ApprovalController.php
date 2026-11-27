<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Enums\OvertimeStatus;
use App\Exceptions\InvalidOvertimeTransition;
use App\Http\Requests\Panel\ApproveOvertimesRequest;
use App\Http\Requests\Panel\OvertimeReasonRequest;
use App\Models\Overtime;
use App\Models\User;
use App\Queries\PanelOvertimeQuery;
use App\Services\OvertimeWorkflow;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ApprovalController
{
    public function index(Request $request, PanelOvertimeQuery $overtimes): Response|RedirectResponse
    {
        $queue = $overtimes->visibleTo($this->actor($request))
            ->where('status', OvertimeStatus::PENDING)
            ->with(['employee:id,name,nik', 'department:id,name'])
            ->orderByDesc('overtime_date')
            ->orderByDesc('start_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->through($overtimes->row(...));

        if ($queue->currentPage() > $queue->lastPage()) {
            $request->session()->reflash();

            return redirect()->to($queue->url($queue->lastPage()));
        }

        return Inertia::render('panel/approvals', ['queue' => $queue]);
    }

    public function approve(Request $request, Overtime $overtime, OvertimeWorkflow $workflow): RedirectResponse
    {
        return $this->apply($request, function () use ($request, $overtime, $workflow): string {
            $workflow->approve($this->actor($request), $overtime, CarbonImmutable::now());

            return 'Pengajuan disetujui.';
        });
    }

    public function reject(OvertimeReasonRequest $request, Overtime $overtime, OvertimeWorkflow $workflow): RedirectResponse
    {
        return $this->apply($request, function () use ($request, $overtime, $workflow): string {
            $workflow->reject($this->actor($request), $overtime, (string) $request->string('reason'), CarbonImmutable::now());

            return 'Pengajuan ditolak.';
        });
    }

    public function bulkApprove(ApproveOvertimesRequest $request, OvertimeWorkflow $workflow): RedirectResponse
    {
        return $this->apply($request, function () use ($request, $workflow): string {
            $count = $workflow->approveMany($this->actor($request), $request->overtimeIds(), CarbonImmutable::now())->count();

            return "{$count} pengajuan disetujui.";
        });
    }

    /** @param callable(): string $action */
    private function apply(Request $request, callable $action): RedirectResponse
    {
        $page = $request->integer('page', 1, strict: true);
        $redirect = to_route('panel.approvals.index', $page > 1 ? ['page' => $page] : [], 303);

        try {
            $message = $action();
        } catch (InvalidOvertimeTransition $exception) {
            return $redirect->withErrors(['status' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return $redirect;
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}

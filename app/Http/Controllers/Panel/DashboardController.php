<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Enums\OvertimeStatus;
use App\Models\User;
use App\Queries\PanelOvertimeQuery;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController
{
    public function index(Request $request, PanelOvertimeQuery $overtimes): Response
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $month = CarbonImmutable::now()->startOfMonth();
        $visible = $overtimes->visibleTo($actor);
        $pending = (clone $visible)->where('status', OvertimeStatus::PENDING);
        $approved = $visible->where('status', OvertimeStatus::APPROVED)
            ->whereDate('overtime_date', '>=', $month->toDateString())
            ->whereDate('overtime_date', '<', $month->addMonth()->toDateString());

        return Inertia::render('panel/dashboard', [
            'monthLabel' => $month->settings(['locale' => 'id'])->translatedFormat('F Y'),
            'kpis' => [
                'pendingCount' => $pending->count(),
                'approvedCount' => $approved->count(),
                'approvedMinutes' => (int) $approved->sum('total_minutes'),
                'overLimitCount' => (clone $pending)
                    ->where(fn ($query) => $query->where('exceeds_daily_limit', true)->orWhere('exceeds_weekly_limit', true))
                    ->count(),
            ],
            'recentPending' => $pending->with(['employee:id,name,nik', 'department:id,name'])
                ->orderByDesc('overtime_date')
                ->orderByDesc('start_at')
                ->orderByDesc('id')
                ->limit(5)
                ->get()
                ->map($overtimes->row(...)),
            'approvalsUrl' => Route::has('panel.approvals.index') ? route('panel.approvals.index', absolute: false) : null,
        ]);
    }
}

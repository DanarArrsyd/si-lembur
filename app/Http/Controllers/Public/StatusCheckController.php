<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Enums\OvertimeSource;
use App\Enums\OvertimeStatus;
use App\Models\Overtime;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class StatusCheckController
{
    public const HISTORY_DAYS = 60;

    public function show(): Response
    {
        return Inertia::render('public/status', ['history' => null]);
    }

    public function history(Request $request, string $number): HttpResponse
    {
        $submission = Overtime::query()
            ->with('employee:id,name,is_active')
            ->where('source', OvertimeSource::PUBLIC)
            ->where('number', $number)
            ->firstOrFail(['employee_id']);

        if (! $submission->employee->is_active) {
            return self::invalidLink($request);
        }

        $today = CarbonImmutable::today();
        $items = Overtime::query()
            ->where('employee_id', $submission->employee_id)
            ->whereDate('overtime_date', '>=', $today->subDays(self::HISTORY_DAYS)->toDateString())
            ->whereDate('overtime_date', '<=', $today->toDateString())
            ->orderByDesc('overtime_date')
            ->orderByDesc('start_at')
            ->orderByDesc('id')
            ->get(['number', 'overtime_date', 'start_at', 'end_at', 'total_minutes', 'status', 'reject_reason'])
            ->map(fn (Overtime $overtime): array => [
                'number' => $overtime->number,
                'date' => $overtime->overtime_date->toDateString(),
                'start_at' => $overtime->start_at->format('Y-m-d H:i'),
                'end_at' => $overtime->end_at->format('Y-m-d H:i'),
                'total_minutes' => $overtime->total_minutes,
                'status' => $overtime->status->value,
                'reject_reason' => $overtime->status === OvertimeStatus::REJECTED ? $overtime->reject_reason : null,
            ]);

        return self::privateResponse($request, Inertia::render('public/status', [
            'history' => [
                'employeeName' => $submission->employee->name,
                'items' => $items,
            ],
        ]));
    }

    public static function invalidLink(Request $request): HttpResponse
    {
        return self::privateResponse($request, Inertia::render('public/status', [
            'history' => null,
            'invalidLink' => true,
        ]))->setStatusCode(403);
    }

    private static function privateResponse(Request $request, Response $page): HttpResponse
    {
        $response = $page->toResponse($request);
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}

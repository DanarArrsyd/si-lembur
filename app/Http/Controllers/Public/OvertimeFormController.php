<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Enums\OvertimeSource;
use App\Http\Requests\PublicOvertimeRequest;
use App\Models\Department;
use App\Models\Overtime;
use App\Services\OvertimeDateRule;
use App\Services\OvertimeWorkflow;
use App\Services\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class OvertimeFormController
{
    public function show(OvertimeDateRule $dates, Settings $settings): Response
    {
        $now = CarbonImmutable::now();

        return Inertia::render('public/form', [
            'departments' => Department::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->orderBy('id')
                ->get(['id', 'name'])
                ->map(fn (Department $department): array => ['id' => $department->id, 'name' => $department->name]),
            'dateChoices' => array_map(static fn (array $entry): array => [
                'value' => $entry['choice']->value,
                'label' => sprintf('%s (%s)', $entry['choice']->label(), $entry['date']->settings(['locale' => 'id'])->translatedFormat('d M Y')),
                'date' => $entry['date']->toDateString(),
            ], $dates->publicChoices($now)),
            'serverTime' => $now->format('H:i'),
            'maxGrossMinutes' => $settings->maxGrossMinutes(),
        ]);
    }

    public function review(PublicOvertimeRequest $request, OvertimeWorkflow $workflow, Settings $settings): RedirectResponse
    {
        Inertia::flash('review', [
            ...$workflow->previewPublic($request->overtimeInput(), CarbonImmutable::now()),
            'daily_limit_minutes' => $settings->dailyLimitMinutes(),
            'weekly_limit_minutes' => $settings->weeklyLimitMinutes(),
        ]);

        return to_route('home', status: 303);
    }

    public function store(PublicOvertimeRequest $request, OvertimeWorkflow $workflow): RedirectResponse
    {
        $now = CarbonImmutable::now();
        $overtime = $workflow->submitPublic($request->overtimeInput(), $now);

        return redirect()->to(URL::temporarySignedRoute('public.success', $now->addDay(), [
            'number' => $overtime->number,
        ]), 303);
    }

    public function success(Request $request, string $number): HttpResponse
    {
        $overtime = Overtime::query()
            ->with(['employee:id,name,nik,is_active', 'department:id,name'])
            ->where('source', OvertimeSource::PUBLIC)
            ->where('number', $number)
            ->firstOrFail();

        $response = Inertia::render('public/success', [
            'submission' => [
                'number' => $overtime->number,
                'status' => $overtime->status->value,
                'status_url' => $overtime->employee->is_active
                    ? URL::temporarySignedRoute(
                        'public.status.history',
                        ($overtime->created_at ?? CarbonImmutable::now())->toImmutable()->addDays(StatusCheckController::HISTORY_DAYS),
                        ['number' => $overtime->number],
                    )
                    : null,
                'date' => $overtime->overtime_date->toDateString(),
                'start_at' => $overtime->start_at->format('Y-m-d H:i'),
                'end_at' => $overtime->end_at->format('Y-m-d H:i'),
                'break_minutes' => $overtime->break_minutes,
                'total_minutes' => $overtime->total_minutes,
                'employee' => ['name' => $overtime->employee->name, 'nik' => $overtime->employee->nik],
                'department' => $overtime->department->name,
                'description' => $overtime->description,
            ],
        ])->toResponse($request);
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}

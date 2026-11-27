<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

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
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class OvertimeFormController
{
    public function show(OvertimeDateRule $dates, Settings $settings): InertiaResponse
    {
        $now = CarbonImmutable::now();

        return Inertia::render('public/form', [
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'dateChoices' => array_map(fn (array $option): array => [
                'value' => $option['choice']->value,
                'date' => $option['date']->toDateString(),
                'label' => sprintf('%s (%s)', $option['choice']->label(), $option['date']->settings(['locale' => 'id'])->translatedFormat('d M Y')),
            ], $dates->publicChoices($now)),
            'serverTime' => $now->format('H:i'),
            'maxGrossMinutes' => $settings->maxGrossMinutes(),
        ]);
    }

    public function preview(PublicOvertimeRequest $request, OvertimeWorkflow $workflow, Settings $settings): RedirectResponse
    {
        Inertia::flash('review', [
            ...$workflow->previewPublic($request->overtimeInput(), CarbonImmutable::now()),
            'daily_limit_minutes' => $settings->dailyLimitMinutes(),
            'weekly_limit_minutes' => $settings->weeklyLimitMinutes(),
        ]);

        return to_route('home', status: 303);
    }

    public function submit(PublicOvertimeRequest $request, OvertimeWorkflow $workflow): RedirectResponse
    {
        $now = CarbonImmutable::now();
        $overtime = $workflow->submitPublic($request->overtimeInput(), $now);

        return redirect()->to(URL::temporarySignedRoute('public.success', $now->addDay(), [
            'number' => $overtime->number,
        ]), 303);
    }

    public function success(Request $request, string $number): Response
    {
        $overtime = Overtime::query()
            ->with(['employee:id,name,nik', 'department:id,name'])
            ->where('number', $number)
            ->firstOrFail();

        $response = Inertia::render('public/success', [
            'number' => $overtime->number,
            'status' => $overtime->status->value,
            'details' => [
                'date' => $overtime->overtime_date->toDateString(),
                'start_at' => $overtime->start_at->format('Y-m-d H:i'),
                'end_at' => $overtime->end_at->format('Y-m-d H:i'),
                'break_minutes' => $overtime->break_minutes,
                'total_minutes' => $overtime->total_minutes,
                'day_type' => $overtime->day_type->value,
                'employee' => ['name' => $overtime->employee->name, 'nik' => $overtime->employee->nik],
                'department' => $overtime->department->name,
                'description' => $overtime->description,
            ],
            'statusUrl' => '/status?'.http_build_query([
                'department' => $overtime->department_id,
                'employee' => $overtime->employee_id,
            ]),
        ])->toResponse($request);

        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}

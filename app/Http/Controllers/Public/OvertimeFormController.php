<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Requests\PublicOvertimeRequest;
use App\Models\Department;
use App\Services\OvertimeDateRule;
use App\Services\OvertimeWorkflow;
use App\Services\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class OvertimeFormController
{
    public function show(OvertimeDateRule $dates, Settings $settings): Response
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
}

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
}

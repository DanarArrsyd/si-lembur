<?php

declare(strict_types=1);

use App\Enums\OvertimeSource;
use App\Enums\OvertimeStatus;
use App\Models\Employee;
use App\Models\Overtime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->now = CarbonImmutable::parse('2026-11-27 09:00');
    $this->travelTo($this->now);
    $this->employee = Employee::factory()->create(['name' => 'Budi Santoso']);
    $this->overtime = Overtime::factory()->for($this->employee)->create([
        'overtime_date' => '2026-11-26',
        'start_at' => '2026-11-26 23:00',
        'end_at' => '2026-11-27 02:00',
        'total_minutes' => 150,
        'status' => OvertimeStatus::REJECTED,
        'reject_reason' => 'Jam belum sesuai dengan catatan.',
    ]);
});

it('links the receipt to a private history that expires 60 days after submission', function () {
    $receipt = URL::temporarySignedRoute('public.success', $this->now->addDay(), ['number' => $this->overtime->number]);
    $history = URL::temporarySignedRoute('public.status.history', $this->now->addDays(60), ['number' => $this->overtime->number]);

    $this->get($receipt)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('submission.status_url', $history));

    $this->travel(2)->days();
    $this->get($receipt)->assertForbidden();
    $this->get($history)->assertOk();

    $this->travelTo($this->now->addDays(60)->addSecond());
    $this->get($history)->assertForbidden()->assertInertia(fn (Assert $page) => $page
        ->component('public/status')
        ->where('history', null)
        ->where('invalidLink', true));
});

it('returns only the signed employee history including day 60 and excludes private form details', function () {
    $boundary = Overtime::factory()->for($this->employee)->create([
        'overtime_date' => $this->now->subDays(60)->toDateString(),
        'source' => OvertimeSource::SUPERVISOR,
    ]);
    Overtime::factory()->for($this->employee)->create(['overtime_date' => $this->now->subDays(61)->toDateString()]);
    Overtime::factory()->for(Employee::factory()->for($this->employee->department))->create(['overtime_date' => '2026-11-27']);

    $history = URL::temporarySignedRoute('public.status.history', $this->now->addDay(), ['number' => $this->overtime->number]);
    $response = $this->get($history)->assertOk()
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/status')
            ->where('history.employeeName', 'Budi Santoso')
            ->has('history.items', 2)
            ->where('history.items.0', [
                'number' => $this->overtime->number,
                'date' => '2026-11-26',
                'start_at' => '2026-11-26 23:00',
                'end_at' => '2026-11-27 02:00',
                'total_minutes' => 150,
                'status' => 'REJECTED',
                'reject_reason' => 'Jam belum sesuai dengan catatan.',
            ])
            ->where('history.items.1.number', $boundary->number)
            ->missing('history.employeeId')
            ->missing('history.items.1.description'));
    expect($response->headers->hasCacheControlDirective('no-store'))->toBeTrue();
});

it('offers public help without exposing records and rejects unsigned history', function () {
    $this->get('/status?employee_id='.$this->employee->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('public/status')->where('history', null));
    $this->get(route('public.status.history', ['number' => $this->overtime->number]))->assertForbidden();
    $this->get('/api/status?employee_id='.$this->employee->id)->assertNotFound();
});

it('refuses history when the key employee is inactive', function () {
    $this->employee->update(['is_active' => false]);
    $history = URL::temporarySignedRoute('public.status.history', $this->now->addDay(), ['number' => $this->overtime->number]);

    $this->get($history)
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page->where('history', null)->where('invalidLink', true));
});

it('limits status history to 60 requests per IP per minute', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.21']);
    $history = URL::temporarySignedRoute('public.status.history', $this->now->addDay(), ['number' => $this->overtime->number]);

    for ($request = 0; $request < 60; $request++) {
        $this->get($history)->assertOk();
    }
    $this->get($history)->assertTooManyRequests();
});

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
    $this->travelTo(CarbonImmutable::parse('2026-11-10 11:59'));
    $this->employee = Employee::factory()->create(['name' => 'Budi Santoso']);
    $this->input = [
        'department_id' => $this->employee->department_id,
        'employee_id' => $this->employee->id,
        'date_choice' => 'YESTERDAY',
        'overtime_date' => '2026-11-09',
        'start_time' => '23:00',
        'end_time' => '02:00',
        'break_minutes' => 30,
        'description' => 'Menyelesaikan pesanan',
        'website' => '',
    ];
});

it('stores a pending overtime and redirects to a private signed receipt for 24 hours', function () {
    $response = $this->post('/lembur', $this->input)->assertStatus(303);
    $overtime = Overtime::query()->sole();
    expect($overtime->status)->toBe(OvertimeStatus::PENDING)
        ->and($overtime->source)->toBe(OvertimeSource::PUBLIC)
        ->and($overtime->total_minutes)->toBe(150)
        ->and($overtime->overtime_date->toDateString())->toBe('2026-11-09');
    $this->assertDatabaseHas('audit_logs', ['action' => 'overtime.submitted', 'auditable_id' => $overtime->id]);

    $url = URL::temporarySignedRoute('public.success', CarbonImmutable::now()->addDay(), ['number' => $overtime->number]);
    $response->assertRedirect($url);
    $receipt = $this->get($url)
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertInertia(fn (Assert $page) => $page->component('public/success')
            ->where('submission.number', $overtime->number)
            ->where('submission.employee.name', 'Budi Santoso')
            ->where('submission.date', '2026-11-09')
            ->where('submission.end_at', '2026-11-10 02:00')
            ->where('submission.total_minutes', 150)
            ->where('submission.status', 'PENDING')
            ->missing('submission.employee_id')
            ->missing('submission.submitted_ip'));
    expect($receipt->headers->hasCacheControlDirective('no-store'))->toBeTrue();

    $this->travel(24)->hours();
    $this->travel(1)->second();
    $this->get($url)->assertForbidden()->assertInertia(fn (Assert $page) => $page
        ->component('public/success')
        ->where('submission', null));
});

it('refuses unsigned receipts and returns 404 for a signed unknown number', function () {
    $overtime = Overtime::factory()->create(['number' => 'LBR-20261110-0001']);

    $this->get(route('public.success', ['number' => $overtime->number]))->assertForbidden();
    $this->get(URL::temporarySignedRoute('public.success', CarbonImmutable::now()->addDay(), ['number' => 'LBR-20261110-9999']))
        ->assertNotFound();
});

it('validates again at submission and refuses duplicate records', function () {
    $this->post('/lembur', $this->input)->assertStatus(303);
    $overtime = Overtime::query()->sole();
    $this->from('/')->post('/lembur', $this->input)->assertRedirect('/')
        ->assertSessionHasErrors(['start_time' => "Sudah ada lembur {$overtime->number} di jam yang sama."]);

    $this->travel(2)->minutes();
    $this->from('/')->post('/lembur', $this->input)->assertRedirect('/')
        ->assertSessionHasErrors(['overtime_date']);

    $this->assertDatabaseCount('overtimes', 1);
    $this->assertDatabaseCount('audit_logs', 1);
});

it('blocks a filled honeypot and rate limits invalid submissions independently from review', function () {
    $this->postJson('/lembur', [...$this->input, 'website' => 'https://example.test'])->assertUnprocessable();

    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.20']);
    for ($request = 0; $request < 10; $request++) {
        $this->post('/lembur', [])->assertSessionHasErrors(['employee_id']);
    }
    $this->post('/lembur', [])->assertTooManyRequests();
    $this->post('/lembur/periksa', $this->input)->assertStatus(303);
    $this->assertDatabaseCount('overtimes', 0);
});

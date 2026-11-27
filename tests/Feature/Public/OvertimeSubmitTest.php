<?php

declare(strict_types=1);

use App\Enums\OvertimeStatus;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Overtime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-10 21:00', 'Asia/Jakarta'));
    $this->employee = Employee::factory()->create();
    $this->payload = [
        'department_id' => $this->employee->department_id,
        'employee_id' => $this->employee->id,
        'date_choice' => 'TODAY',
        'overtime_date' => '2026-11-10',
        'start_time' => '17:00',
        'end_time' => '20:00',
        'break_minutes' => 15,
        'description' => 'Menyelesaikan pesanan produksi.',
        'website' => '',
    ];
});

it('stores one pending overtime and redirects to its signed receipt', function () {
    $response = $this->from('/')->post('/lembur', $this->payload)->assertStatus(303);
    $overtime = Overtime::query()->sole();

    expect($overtime->status)->toBe(OvertimeStatus::PENDING)
        ->and($overtime->total_minutes)->toBe(165);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'overtime.submitted',
        'auditable_id' => $overtime->id,
    ]);

    $this->get($response->headers->get('Location'))
        ->assertOk()
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/success')
            ->where('number', $overtime->number)
            ->where('status', 'PENDING')
            ->where('details.date', '2026-11-10')
            ->where('details.total_minutes', 165)
            ->where('details.employee.nik', $this->employee->nik)
            ->where('statusUrl', '/status?department='.$overtime->department_id.'&employee='.$overtime->employee_id)
            ->missing('details.submitted_ip'));
});

it('rejects an unsigned receipt and a signed URL whose number does not exist', function () {
    $this->get('/lembur/sukses/LBR-20261110-0001')->assertForbidden();

    $unknown = URL::temporarySignedRoute('public.success', now()->addDay(), ['number' => 'LBR-20261110-9999']);
    $this->get($unknown)->assertNotFound();
});

it('expires after 24 hours and directs the employee to the status page', function () {
    $response = $this->from('/')->post('/lembur', $this->payload)->assertStatus(303);
    $receipt = $response->headers->get('Location');

    $this->travel(23)->hours();
    $this->get($receipt)->assertOk();

    $this->travel(61)->minutes();
    $this->get($receipt)
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/success-unavailable')
            ->missing('details'));
});

it('revalidates the reviewed date at midnight and requires it on submit', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-11 00:01', 'Asia/Jakarta'));

    $this->from('/')->post('/lembur', $this->payload)
        ->assertSessionHasErrors(['overtime_date' => 'Tanggal lembur sudah berubah. Pilih tanggal lagi.']);

    $this->from('/')->post('/lembur', [...$this->payload, 'overtime_date' => null])
        ->assertSessionHasErrors(['overtime_date']);

    $this->assertDatabaseEmpty('overtimes');
    $this->assertDatabaseEmpty('audit_logs');
    $this->assertDatabaseEmpty('overtime_number_counters');
});

it('rejects a duplicate and does not allocate a second number', function () {
    $this->from('/')->post('/lembur', $this->payload)->assertStatus(303);
    $this->from('/')->post('/lembur', $this->payload)
        ->assertSessionHasErrors(['start_time' => 'Sudah ada lembur LBR-20261110-0001 di jam yang sama.']);

    $this->assertDatabaseCount('overtimes', 1);
    $this->assertDatabaseCount('audit_logs', 1);
    $this->assertDatabaseHas('overtime_number_counters', ['last_number' => 1]);
});

it('uses a separate submit quota from preview and lookup', function () {
    $lookup = '/api/departments/'.$this->employee->department_id.'/employees';
    $invalid = [...$this->payload, 'description' => 'abc'];

    foreach (range(1, 10) as $_) {
        $this->getJson($lookup)->assertOk();
        $this->postJson('/lembur/periksa', $invalid)->assertUnprocessable();
        $this->postJson('/lembur', $invalid)->assertUnprocessable();
    }

    $this->postJson('/lembur', $invalid)->assertTooManyRequests();
    $this->getJson($lookup)->assertOk();
    $this->assertDatabaseEmpty('overtimes');
});

it('prefills status with the current department after an employee transfer', function () {
    $response = $this->from('/')->post('/lembur', $this->payload)->assertStatus(303);
    $previousName = $this->employee->department->name;
    $newDepartment = Department::factory()->create();
    $this->employee->update(['department_id' => $newDepartment->id]);

    $this->get($response->headers->get('Location'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('details.department', $previousName)
            ->where('statusUrl', '/status?department='.$newDepartment->id.'&employee='.$this->employee->id));
});

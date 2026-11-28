<?php

declare(strict_types=1);

use App\Enums\OvertimeStatus;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Overtime;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-28 12:00:00', 'Asia/Jakarta'));
    $this->department = Department::factory()->create();
    $this->supervisor = User::factory()->supervisor($this->department)->create();
});

it('combines history filters with the saved department and uses the start month', function () {
    $other = Department::factory()->create();
    $employee = Employee::factory()->for($other)->create();
    $historic = Overtime::factory()->for($employee)->for($this->department)->create([
        'overtime_date' => '2026-10-31',
        'start_at' => '2026-10-31 23:00:00',
        'end_at' => '2026-11-01 02:00:00',
        'status' => OvertimeStatus::APPROVED,
    ]);
    Overtime::factory()->for($employee)->for($this->department)->create(['status' => OvertimeStatus::APPROVED]);
    Overtime::factory()->for($employee)->for($this->department)->create(['overtime_date' => '2026-10-30']);
    Overtime::factory()->for($this->department)->create(['overtime_date' => '2026-10-30', 'status' => OvertimeStatus::APPROVED]);
    Overtime::factory()->for($employee)->create(['overtime_date' => '2026-10-30', 'status' => OvertimeStatus::APPROVED]);

    $this->actingAs($this->supervisor)
        ->get('/panel/overtimes?month=2026-10&status=APPROVED&employee_id='.$employee->id)
        ->assertInertia(fn (Assert $page) => $page
            ->component('panel/overtimes/index')
            ->where('history.total', 1)
            ->where('history.data.0.id', $historic->id)
            ->where('history.data.0.department.id', $this->department->id)
            ->where('history.data.0.can_cancel_approval', true)
            ->has('employees', 2));
});

it('shows all statuses across departments to an admin and handles invalid filters', function () {
    $local = Overtime::factory()->for($this->department)->create();
    $foreign = Overtime::factory()->create(['status' => OvertimeStatus::REJECTED]);

    $this->actingAs(User::factory()->admin()->create())
        ->get('/panel/overtimes?month[]=x&status[]=x&employee_id[]=1')
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.month', '2026-11')
            ->where('filters.status', null)
            ->where('filters.employee_id', null)
            ->where('history.total', 2)
            ->where('history.data.0.id', $foreign->id)
            ->where('history.data.1.id', $local->id));
});

it('cancels approval at the start of the previous month and keeps the filters', function () {
    $entry = Overtime::factory()->for($this->department)->create([
        'overtime_date' => '2026-10-01',
        'start_at' => '2026-10-01 17:00:00',
        'end_at' => '2026-10-01 20:00:00',
        'status' => OvertimeStatus::APPROVED,
        'approved_by' => $this->supervisor->id,
        'approved_at' => '2026-10-02 10:00:00',
    ]);
    $url = "/panel/overtimes/{$entry->id}/cancel-approval?month=2026-10&status=APPROVED";
    $this->actingAs($this->supervisor)->post($url, ['reason' => '   '])->assertSessionHasErrors('reason');
    $this->assertDatabaseEmpty('audit_logs');
    $this->post($url, ['reason' => '  Periksa kembali jam selesai.  '])
        ->assertStatus(303)
        ->assertRedirect('/panel/overtimes?month=2026-10&status=APPROVED')
        ->assertInertiaFlash('toast.type', 'success');

    expect($entry->refresh()->status)->toBe(OvertimeStatus::PENDING)
        ->and($entry->approved_by)->toBeNull()
        ->and($entry->approved_at)->toBeNull();
    $audit = AuditLog::query()->sole();
    expect($audit->action)->toBe('overtime.approval_cancelled')
        ->and($audit->reason)->toBe('Periksa kembali jam selesai.')
        ->and($audit->before['status'])->toBe('APPROVED')
        ->and($audit->after['status'])->toBe('PENDING')
        ->and($audit->created_at->toDateTimeString())->toBe('2026-11-28 12:00:00');
});

it('keeps an older approval intact and forbids cross-department cancellation', function () {
    $old = Overtime::factory()->for($this->department)->create([
        'overtime_date' => '2026-09-30', 'status' => OvertimeStatus::APPROVED,
    ]);
    $foreign = Overtime::factory()->create(['status' => OvertimeStatus::APPROVED]);

    $this->actingAs($this->supervisor)->get('/panel/overtimes?month=2026-09')
        ->assertInertia(fn (Assert $page) => $page->where('history.data.0.can_cancel_approval', false));
    $this->post("/panel/overtimes/{$old->id}/cancel-approval", ['reason' => 'Periksa kembali jam selesai.'])
        ->assertSessionHasErrors('overtime_date');
    $this->post("/panel/overtimes/{$foreign->id}/cancel-approval", ['reason' => ''])
        ->assertForbidden();

    expect($old->refresh()->status)->toBe(OvertimeStatus::APPROVED)
        ->and($foreign->refresh()->status)->toBe(OvertimeStatus::APPROVED);
    $this->assertDatabaseEmpty('audit_logs');
});

it('allows an admin to cancel across departments and rejects a duplicate cancellation', function () {
    $entry = Overtime::factory()->create(['status' => OvertimeStatus::APPROVED]);
    $data = ['reason' => 'Periksa kembali jam selesai.'];
    $url = "/panel/overtimes/{$entry->id}/cancel-approval";

    $this->actingAs(User::factory()->admin()->create())->post($url, $data)->assertStatus(303);
    $this->post($url, $data)->assertSessionHasErrors('status');

    expect($entry->refresh()->status)->toBe(OvertimeStatus::PENDING);
    $this->assertDatabaseCount('audit_logs', 1);
});

it('checks the window inside the workflow and uses the supplied time for the audit', function () {
    $entry = Overtime::factory()->for($this->department)->create([
        'overtime_date' => '2026-10-01', 'status' => OvertimeStatus::APPROVED,
    ]);
    expect(validationErrors(fn () => workflow()->cancelApproval(
        $this->supervisor, $entry, 'Periksa kembali jam selesai.', CarbonImmutable::parse('2026-12-01 00:00'),
    )))->toHaveKey('overtime_date');
    $this->assertDatabaseEmpty('audit_logs');

    $now = CarbonImmutable::now()->subMinute();
    workflow()->cancelApproval($this->supervisor, $entry, 'Periksa kembali jam selesai.', $now);
    expect(AuditLog::query()->sole()->created_at->toDateTimeString())->toBe($now->toDateTimeString());
});

<?php

declare(strict_types=1);

use App\Enums\OvertimeStatus;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Overtime;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-28 12:00:00', 'Asia/Jakarta'));
    $this->department = Department::factory()->create();
    $this->supervisor = User::factory()->supervisor($this->department)->create();
});

it('paginates only pending entries from the supervisor department', function () {
    Overtime::factory()->count(26)->for($this->department)->create();
    Overtime::factory()->for($this->department)->create(['status' => OvertimeStatus::APPROVED]);
    Overtime::factory()->create();

    $this->actingAs($this->supervisor)->get('/panel/approvals')
        ->assertInertia(fn (Assert $page) => $page->component('panel/approvals')
            ->where('queue.total', 26)->has('queue.data', 25)
            ->where('queue.data.0.department.id', $this->department->id)
            ->where('queue.data.24.department.id', $this->department->id));

    $this->get('/panel/approvals?page=2')
        ->assertInertia(fn (Assert $page) => $page->has('queue.data', 1)->where('queue.current_page', 2));
    $this->get('/panel/approvals?page=3')->assertRedirect('/panel/approvals?page=2');
});

it('forbids every mutation targeting a different department', function () {
    $local = Overtime::factory()->for($this->department)->create();
    $foreign = Overtime::factory()->create();

    $this->actingAs($this->supervisor);
    $this->post("/panel/approvals/{$foreign->id}/approve")->assertForbidden();
    $this->post("/panel/approvals/{$foreign->id}/reject", ['reason' => ''])->assertForbidden();
    $this->post('/panel/approvals/bulk-approve', ['ids' => [$local->id, $foreign->id]])->assertForbidden();

    expect($local->refresh()->status)->toBe(OvertimeStatus::PENDING)
        ->and($foreign->refresh()->status)->toBe(OvertimeStatus::PENDING);
    $this->assertDatabaseEmpty('audit_logs');
});

it('bulk approves across departments for an admin and writes one audit per row', function () {
    $admin = User::factory()->admin()->create();
    $first = Overtime::factory()->for($this->department)->create();
    $second = Overtime::factory()->create();

    $this->actingAs($admin)->get('/panel/approvals')
        ->assertInertia(fn (Assert $page) => $page->where('queue.total', 2));
    $this->post('/panel/approvals/bulk-approve', ['ids' => [$first->id, $second->id]])
        ->assertStatus(303)
        ->assertRedirect('/panel/approvals')
        ->assertInertiaFlash('toast.type', 'success');

    foreach ([$first, $second] as $overtime) {
        $overtime->refresh();
        expect($overtime->status)->toBe(OvertimeStatus::APPROVED)
            ->and($overtime->approved_by)->toBe($admin->id)
            ->and($overtime->approved_at?->toDateTimeString())->toBe('2026-11-28 12:00:00');
        $audit = AuditLog::query()->where('auditable_id', $overtime->id)->sole();
        expect($audit->action)->toBe('overtime.approved')
            ->and($audit->user_id)->toBe($admin->id)
            ->and($audit->created_at->toDateTimeString())->toBe($overtime->approved_at?->toDateTimeString());
    }
});

it('rejects an entire stale bulk request and preserves the current page', function () {
    $first = Overtime::factory()->for($this->department)->create();
    $stale = Overtime::factory()->for($this->department)->create(['status' => OvertimeStatus::APPROVED]);

    $this->actingAs($this->supervisor)->post('/panel/approvals/bulk-approve?page=2', ['ids' => [$first->id, $stale->id]])
        ->assertStatus(303)
        ->assertRedirect('/panel/approvals?page=2')
        ->assertSessionHasErrors(['status' => 'Pengajuan ini sudah disetujui. Muat ulang halaman.']);

    expect($first->refresh()->status)->toBe(OvertimeStatus::PENDING);
    $this->assertDatabaseEmpty('audit_logs');
});

it('keeps the stale status message when the requested page is no longer available', function () {
    $stale = Overtime::factory()->for($this->department)->create(['status' => OvertimeStatus::APPROVED]);

    $this->actingAs($this->supervisor)->followingRedirects()
        ->post("/panel/approvals/{$stale->id}/approve?page=2")
        ->assertInertia(fn (Assert $page) => $page
            ->where('queue.current_page', 1)
            ->where('errors.status', 'Pengajuan ini sudah disetujui. Muat ulang halaman.'));
});

it('rejects malformed batch IDs without making changes', function (array $ids) {
    $this->actingAs($this->supervisor)->post('/panel/approvals/bulk-approve', ['ids' => $ids])
        ->assertSessionHasErrors();
    $this->assertDatabaseEmpty('audit_logs');
})->with([
    'empty' => [[]],
    'duplicate' => [[1, '1']],
    'not a positive integer' => [[0]],
    'associative' => [['a' => 1]],
    'too many' => [range(1, 26)],
]);

it('validates a trimmed reason and saves a rejection', function () {
    $overtime = Overtime::factory()->for($this->department)->create();
    $url = "/panel/approvals/{$overtime->id}/reject";

    $this->actingAs($this->supervisor)->post($url, ['reason' => '  '])->assertSessionHasErrors('reason');
    $this->post($url, ['reason' => str_repeat('a', 501)])->assertSessionHasErrors('reason');
    expect($overtime->refresh()->status)->toBe(OvertimeStatus::PENDING);
    $this->assertDatabaseEmpty('audit_logs');

    $this->post($url, ['reason' => '  Jam selesai perlu diperiksa.  '])
        ->assertStatus(303)->assertRedirect('/panel/approvals');
    $overtime->refresh();
    expect($overtime->status)->toBe(OvertimeStatus::REJECTED)
        ->and($overtime->reject_reason)->toBe('Jam selesai perlu diperiksa.');
    $audit = AuditLog::query()->sole();
    expect($audit->reason)->toBe($overtime->reject_reason)
        ->and($audit->created_at->toDateTimeString())->toBe($overtime->rejected_at?->toDateTimeString());
});

it('approves one entry and logs it once on repeated requests', function () {
    $overtime = Overtime::factory()->for($this->department)->create();
    $url = "/panel/approvals/{$overtime->id}/approve";

    $this->actingAs($this->supervisor)->post($url)->assertStatus(303)->assertRedirect('/panel/approvals');
    $this->post($url)->assertSessionHasErrors('status');

    expect($overtime->refresh()->status)->toBe(OvertimeStatus::APPROVED);
    $this->assertDatabaseCount('audit_logs', 1);
});

it('uses the supplied workflow timestamp for the entry and its audit', function () {
    $overtime = Overtime::factory()->for($this->department)->create();
    $now = CarbonImmutable::now()->subMinute();
    $updated = workflow()->approve($this->supervisor, $overtime, $now);

    expect($updated->approved_at?->toDateTimeString())->toBe($now->toDateTimeString())
        ->and(AuditLog::query()->sole()->created_at->toDateTimeString())->toBe($now->toDateTimeString());
});

it('rejects a long reason even when the workflow is called directly', function () {
    $overtime = Overtime::factory()->for($this->department)->create();

    expect(validationErrors(fn () => workflow()->reject($this->supervisor, $overtime, str_repeat('a', 501))))
        ->toHaveKey('reason');
    $this->assertDatabaseEmpty('audit_logs');
});

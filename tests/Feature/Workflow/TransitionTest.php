<?php

declare(strict_types=1);

use App\Enums\OvertimeStatus;
use App\Exceptions\InvalidOvertimeTransition;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Overtime;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function () {
    Carbon\Carbon::setTestNow('2026-11-10 09:00');
    $this->department = Department::factory()->create();
    $this->supervisor = User::factory()->supervisor($this->department)->create();
    $this->employee = Employee::factory()->for($this->department)->create();
    $this->overtime = workflow()->submitPublic(overtimeInput($this->employee), CarbonImmutable::parse('2026-11-10 08:00'));
    AuditLog::query()->delete();
});

it('approves a pending overtime', function () {
    $approved = workflow()->approve($this->supervisor, $this->overtime);

    expect($approved)
        ->status->toBe(OvertimeStatus::APPROVED)
        ->approved_by->toBe($this->supervisor->id)
        ->approved_at->format('Y-m-d H:i')->toBe('2026-11-10 09:00')
        ->and(AuditLog::query()->sole())
        ->action->toBe('overtime.approved')
        ->before->toMatchArray(['status' => 'PENDING'])
        ->after->toMatchArray(['status' => 'APPROVED']);
});

it('rejects with a required reason and allows resubmission (PRD §8 #7)', function () {
    expect(validationErrors(fn () => workflow()->reject($this->supervisor, $this->overtime, '   ')))
        ->toBe(['reason' => ['Tulis alasan minimal 5 huruf.']])
        ->and($this->overtime->fresh()->status)->toBe(OvertimeStatus::PENDING);

    $rejected = workflow()->reject($this->supervisor, $this->overtime, '  Jam tidak sesuai absen ');

    expect($rejected)
        ->status->toBe(OvertimeStatus::REJECTED)
        ->rejected_by->toBe($this->supervisor->id)
        ->reject_reason->toBe('Jam tidak sesuai absen')
        ->and(AuditLog::query()->sole()->reason)->toBe('Jam tidak sesuai absen');

    $again = workflow()->submitPublic(overtimeInput($this->employee), CarbonImmutable::parse('2026-11-10 09:30'));
    expect($again->id)->not->toBe($this->overtime->id);
});

it('cancels an approval back to pending with a reason (PRD §8 #8)', function () {
    workflow()->approve($this->supervisor, $this->overtime);

    $pending = workflow()->cancelApproval($this->supervisor, $this->overtime->fresh(), 'Salah jam selesai');

    expect($pending)
        ->status->toBe(OvertimeStatus::PENDING)
        ->approved_by->toBeNull()
        ->approved_at->toBeNull()
        ->and(AuditLog::query()->where('action', 'overtime.approval_cancelled')->sole())
        ->reason->toBe('Salah jam selesai')
        ->user_id->toBe($this->supervisor->id);
});

it('refuses transitions from the wrong status', function (string $setup, Closure $action) {
    if ($setup === 'approved') {
        workflow()->approve($this->supervisor, $this->overtime);
    }

    expect(fn () => $action($this->supervisor, $this->overtime->fresh()))
        ->toThrow(InvalidOvertimeTransition::class);
})->with([
    'approve twice' => ['approved', fn ($u, $o) => workflow()->approve($u, $o)],
    'reject approved' => ['approved', fn ($u, $o) => workflow()->reject($u, $o, 'Alasan panjang')],
    'cancel pending' => ['pending', fn ($u, $o) => workflow()->cancelApproval($u, $o, 'Alasan panjang')],
    'edit approved' => ['approved', fn ($u, $o) => workflow()->update($u, $o, overtimeInput($o->employee, ['dateChoice' => null, 'date' => CarbonImmutable::parse('2026-11-10')]), CarbonImmutable::now())],
]);

it('uses the current status in the transition message', function () {
    workflow()->approve($this->supervisor, $this->overtime);

    expect(fn () => workflow()->approve($this->supervisor, $this->overtime->fresh()))
        ->toThrow(InvalidOvertimeTransition::class, 'Pengajuan ini sudah disetujui. Muat ulang halaman.');
});

it('forbids supervisors of another department on every action (PRD §8 #6)', function (Closure $action) {
    $other = User::factory()->supervisor(Department::factory()->create())->create();

    expect(fn () => $action($other, $this->overtime))->toThrow(AuthorizationException::class)
        ->and($this->overtime->fresh()->status)->toBe(OvertimeStatus::PENDING)
        ->and(AuditLog::query()->count())->toBe(0);
})->with([
    'approve' => [fn ($u, $o) => workflow()->approve($u, $o)],
    'reject' => [fn ($u, $o) => workflow()->reject($u, $o, 'Alasan panjang')],
    'cancel' => [fn ($u, $o) => workflow()->cancelApproval($u, $o, 'Alasan panjang')],
    'update' => [fn ($u, $o) => workflow()->update($u, $o, overtimeInput($o->employee, ['dateChoice' => null, 'date' => CarbonImmutable::parse('2026-11-10')]), CarbonImmutable::now())],
    'bulk' => [fn ($u, $o) => workflow()->approveMany($u, [$o->id])],
]);

it('approves many with one log per record', function () {
    $second = workflow()->submitPublic(overtimeInput($this->employee, ['startTime' => '20:00', 'endTime' => '21:00']), CarbonImmutable::parse('2026-11-10 08:30'));
    AuditLog::query()->delete();

    $approved = workflow()->approveMany($this->supervisor, [$this->overtime->id, $second->id]);

    expect($approved)->toHaveCount(2)
        ->and(Overtime::query()->where('status', OvertimeStatus::APPROVED)->count())->toBe(2)
        ->and(AuditLog::query()->where('action', 'overtime.approved')->count())->toBe(2);
});

it('approves nothing when one id in a bulk is out of scope', function () {
    $foreign = Overtime::factory()->for(Employee::factory()->for(Department::factory()))->create();

    expect(fn () => workflow()->approveMany($this->supervisor, [$this->overtime->id, $foreign->id]))
        ->toThrow(AuthorizationException::class)
        ->and(Overtime::query()->where('status', OvertimeStatus::APPROVED)->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0);
});

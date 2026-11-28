<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OvertimeSource;
use App\Enums\OvertimeStatus;
use App\Exceptions\InvalidOvertimeTransition;
use App\Models\Employee;
use App\Models\Overtime;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Semua perubahan pengajuan lembur (BUSINESS_RULES.md §6). Setiap method berjalan dalam satu
 * transaksi DB, menulis audit log, dan menghitung ulang penanda batas minggu terkait.
 *
 * Otorisasi per data dicek di sini (Policy) selain di controller, supaya jalur mana pun
 * yang memanggil workflow tetap aman. Status yang tidak sesuai → InvalidOvertimeTransition.
 */
class OvertimeWorkflow
{
    /** Transaksi diulang bila InnoDB melaporkan deadlock. */
    private const ATTEMPTS = 3;

    private const MIN_TEXT = 5;

    private const MAX_DESCRIPTION = 500;

    public function __construct(
        private readonly OvertimeCalculator $calculator,
        private readonly OvertimeDateRule $dateRule,
        private readonly DayTypeResolver $dayTypes,
        private readonly OvertimeLimitChecker $limits,
        private readonly OverlapChecker $overlaps,
        private readonly OvertimeNumberGenerator $numbers,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Karyawan mengisi lewat link publik → MENUNGGU.
     *
     * @throws ValidationException
     */
    public function submitPublic(OvertimeInput $input, CarbonImmutable $now): Overtime
    {
        return DB::transaction(function () use ($input, $now) {
            $employee = $this->eligibleEmployee($input, lock: true);
            $description = $this->validDescription($input);

            $date = $this->publicDate($input, $now);
            $times = $this->validatedTimes($employee, $input, $date, completedBy: $now);
            $userAgent = request()->userAgent();

            $overtime = $this->create($employee, $times, $date, $description, $now, [
                'status' => OvertimeStatus::PENDING,
                'source' => OvertimeSource::PUBLIC,
                'submitted_ip' => request()->ip(),
                'submitted_user_agent' => $userAgent === null ? null : Str::limit($userAgent, 500, ''),
            ]);

            $this->audit->log('overtime.submitted', $overtime, after: $this->snapshot($overtime));

            return $overtime;
        }, self::ATTEMPTS);
    }

    /**
     * @return array{date: string, start_at: string, end_at: string, break_minutes: int, total_minutes: int, day_type: string, exceeds_daily_limit: bool, exceeds_weekly_limit: bool, employee: array{name: string, nik: string}, department: string, description: string}
     */
    public function previewPublic(OvertimeInput $input, CarbonImmutable $now): array
    {
        $employee = $this->eligibleEmployee($input);
        $description = $this->validDescription($input);
        $date = $this->publicDate($input, $now);
        $times = $this->validatedTimes($employee, $input, $date, completedBy: $now);
        $dayType = $this->dayTypes->resolve($date);
        $limits = $this->limits->preview($employee->id, $date, $times->startAt, $times->totalMinutes, $dayType);

        return [
            'date' => $date->toDateString(),
            'start_at' => $times->startAt->format('Y-m-d H:i'),
            'end_at' => $times->endAt->format('Y-m-d H:i'),
            'break_minutes' => $times->breakMinutes,
            'total_minutes' => $times->totalMinutes,
            'day_type' => $dayType->value,
            'exceeds_daily_limit' => $limits['daily'],
            'exceeds_weekly_limit' => $limits['weekly'],
            'employee' => ['name' => $employee->name, 'nik' => $employee->nik],
            'department' => $employee->department->name,
            'description' => $description,
        ];
    }

    /**
     * Supervisor/Admin menginput atas nama karyawan (lupa input / tidak punya HP) → langsung DISETUJUI.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function submitBySupervisor(User $actor, OvertimeInput $input, CarbonImmutable $now): Overtime
    {
        if (! $actor->canAccessDepartment($input->departmentId)) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($actor, $input, $now) {
            $employee = $this->eligibleEmployee($input, lock: true);
            $description = $this->validDescription($input);
            $date = $this->panelDate($input, $now);
            $times = $this->validatedTimes($employee, $input, $date);

            $overtime = $this->create($employee, $times, $date, $description, $now, [
                'status' => OvertimeStatus::APPROVED,
                'source' => OvertimeSource::SUPERVISOR,
                'created_by' => $actor->id,
                'approved_by' => $actor->id,
                'approved_at' => $now,
            ]);

            $this->audit->log('overtime.created_by_supervisor', $overtime, after: $this->snapshot($overtime), user: $actor);

            return $overtime;
        }, self::ATTEMPTS);
    }

    /**
     * Edit pengajuan MENUNGGU. Karyawan tidak bisa diganti; semua aturan divalidasi ulang.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function update(User $actor, Overtime $overtime, OvertimeInput $input, CarbonImmutable $now): Overtime
    {
        Gate::forUser($actor)->authorize('update', $overtime);

        return DB::transaction(function () use ($actor, $overtime, $input, $now) {
            $overtime = $this->lockOvertime($overtime, OvertimeStatus::PENDING);

            if ($input->employeeId !== $overtime->employee_id) {
                throw ValidationException::withMessages([
                    'employee_id' => 'Karyawan tidak bisa diganti. Tolak pengajuan ini lalu input ulang.',
                ]);
            }

            Employee::query()->lockForUpdate()->findOrFail($overtime->employee_id);
            $description = $this->validDescription($input);
            $date = $this->panelDate($input, $now);
            $times = $this->calculator->calculate($date, $input->startTime, $input->endTime, $input->breakMinutes);
            $this->overlaps->assertNoConflict($overtime->employee_id, $times->startAt, $times->endAt, $overtime->id);

            $before = $this->snapshot($overtime);
            $previousDate = $overtime->overtime_date->toImmutable();

            $overtime->fill([
                'overtime_date' => $date->toDateString(),
                'start_at' => $times->startAt,
                'end_at' => $times->endAt,
                'break_minutes' => $times->breakMinutes,
                'total_minutes' => $times->totalMinutes,
                'day_type' => $this->dayTypes->resolve($date),
                'description' => $description,
            ])->save();

            $this->limits->recalculateWeek($overtime->employee_id, $previousDate);
            $this->limits->recalculateWeek($overtime->employee_id, $date);
            $overtime->refresh();

            $this->audit->log('overtime.updated', $overtime, $before, $this->snapshot($overtime), user: $actor);

            return $overtime;
        }, self::ATTEMPTS);
    }

    /**
     * @throws AuthorizationException
     */
    public function approve(User $actor, Overtime $overtime, ?CarbonImmutable $now = null): Overtime
    {
        Gate::forUser($actor)->authorize('approve', $overtime);

        $now ??= CarbonImmutable::now();

        return DB::transaction(
            fn () => $this->doApprove($actor, $this->lockOvertime($overtime, OvertimeStatus::PENDING), $now),
            self::ATTEMPTS,
        );
    }

    /**
     * Setujui terpilih. Semua ID dicek Policy dan status dulu; satu saja gagal → tidak ada yang disimpan.
     *
     * @param  list<int>  $ids
     * @return Collection<int, Overtime>
     *
     * @throws AuthorizationException
     */
    public function approveMany(User $actor, array $ids, ?CarbonImmutable $now = null): Collection
    {
        $now ??= CarbonImmutable::now();

        return DB::transaction(function () use ($actor, $ids, $now) {
            $ids = array_values(array_unique($ids));
            $overtimes = Overtime::query()->whereKey($ids)->orderBy('id')->lockForUpdate()->get();

            if ($overtimes->count() !== count($ids)) {
                throw (new ModelNotFoundException)->setModel(Overtime::class, array_diff($ids, $overtimes->modelKeys()));
            }

            foreach ($overtimes as $overtime) {
                Gate::forUser($actor)->authorize('approve', $overtime);
            }

            foreach ($overtimes as $overtime) {
                if ($overtime->status !== OvertimeStatus::PENDING) {
                    throw new InvalidOvertimeTransition($overtime->status);
                }
            }

            return $overtimes->map(fn (Overtime $overtime) => $this->doApprove($actor, $overtime, $now));
        }, self::ATTEMPTS);
    }

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function reject(User $actor, Overtime $overtime, string $reason, ?CarbonImmutable $now = null): Overtime
    {
        Gate::forUser($actor)->authorize('reject', $overtime);
        $reason = $this->validReason($reason);
        $now ??= CarbonImmutable::now();

        return DB::transaction(function () use ($actor, $overtime, $reason, $now) {
            $overtime = $this->lockOvertime($overtime, OvertimeStatus::PENDING);
            $before = $this->snapshot($overtime);

            $overtime->fill([
                'status' => OvertimeStatus::REJECTED,
                'rejected_by' => $actor->id,
                'rejected_at' => $now,
                'reject_reason' => $reason,
            ])->save();

            // Pengajuan ditolak keluar dari hitungan batas; penanda pengajuan lain ikut berubah (H5).
            $this->limits->recalculateWeek($overtime->employee_id, $overtime->overtime_date->toImmutable());

            $this->audit->log('overtime.rejected', $overtime, $before, $this->snapshot($overtime), $reason, $actor, $now);

            return $overtime;
        }, self::ATTEMPTS);
    }

    /**
     * DISETUJUI → MENUNGGU agar bisa dikoreksi. Data penyetuju dikosongkan; riwayatnya ada di audit log.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function cancelApproval(User $actor, Overtime $overtime, string $reason, ?CarbonImmutable $now = null): Overtime
    {
        Gate::forUser($actor)->authorize('cancelApproval', $overtime);
        $reason = $this->validReason($reason);
        $now ??= CarbonImmutable::now();

        return DB::transaction(function () use ($actor, $overtime, $reason, $now) {
            $overtime = $this->lockOvertime($overtime, OvertimeStatus::APPROVED);
            $this->dateRule->assertPanelDate($overtime->overtime_date->toImmutable(), $now);
            $before = $this->snapshot($overtime);

            $overtime->fill([
                'status' => OvertimeStatus::PENDING,
                'approved_by' => null,
                'approved_at' => null,
            ])->save();

            $this->limits->recalculateWeek($overtime->employee_id, $overtime->overtime_date->toImmutable());

            $this->audit->log('overtime.approval_cancelled', $overtime, $before, $this->snapshot($overtime), $reason, $actor, $now);

            return $overtime;
        }, self::ATTEMPTS);
    }

    /**
     * Dipanggil setelah Admin mengubah kalender libur / hari istirahat mingguan (BUSINESS_RULES.md §3):
     * jenis hari pengajuan MENUNGGU di tanggal itu dihitung ulang; DISETUJUI tidak berubah.
     *
     * @param  list<CarbonImmutable>  $dates
     * @return int jumlah pengajuan yang jenis harinya berubah
     */
    public function recalculateDayTypes(array $dates): int
    {
        return DB::transaction(function () use ($dates) {
            $changed = 0;
            $weeks = [];

            foreach ($dates as $date) {
                $dayType = $this->dayTypes->resolve($date);

                $overtimes = Overtime::query()
                    ->where('status', OvertimeStatus::PENDING)
                    ->whereDate('overtime_date', $date->toDateString())
                    ->where('day_type', '!=', $dayType)
                    ->lockForUpdate()
                    ->get();

                foreach ($overtimes as $overtime) {
                    $overtime->update(['day_type' => $dayType]);
                    $weeks[$overtime->employee_id.'|'.$date->startOfWeek(CarbonImmutable::MONDAY)->toDateString()] = [$overtime->employee_id, $date];
                    $changed++;
                }
            }

            foreach ($weeks as [$employeeId, $date]) {
                $this->limits->recalculateWeek($employeeId, $date);
            }

            return $changed;
        }, self::ATTEMPTS);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function create(Employee $employee, OvertimeTimes $times, CarbonImmutable $date, string $description, CarbonImmutable $now, array $attributes): Overtime
    {
        $overtime = Overtime::query()->create([
            'number' => $this->numbers->next($now),
            'employee_id' => $employee->id,
            'department_id' => $employee->department_id,
            'overtime_date' => $date->toDateString(),
            'start_at' => $times->startAt,
            'end_at' => $times->endAt,
            'break_minutes' => $times->breakMinutes,
            'total_minutes' => $times->totalMinutes,
            'day_type' => $this->dayTypes->resolve($date),
            'description' => $description,
            ...$attributes,
        ]);

        $this->limits->recalculateWeek($employee->id, $date);

        return $overtime->refresh();
    }

    private function doApprove(User $actor, Overtime $overtime, CarbonImmutable $now): Overtime
    {
        $before = $this->snapshot($overtime);

        $overtime->fill([
            'status' => OvertimeStatus::APPROVED,
            'approved_by' => $actor->id,
            'approved_at' => $now,
        ])->save();

        $this->audit->log('overtime.approved', $overtime, $before, $this->snapshot($overtime), user: $actor, now: $now);

        return $overtime;
    }

    /**
     * R5 + kunci baris karyawan sampai transaksi selesai, supaya dua pengajuan bersamaan
     * untuk karyawan yang sama diproses bergiliran dan pengecekan overlap (R6) tetap benar.
     *
     * @throws ValidationException
     */
    private function eligibleEmployee(OvertimeInput $input, bool $lock = false): Employee
    {
        $employees = Employee::query()->with('department');
        if ($lock) {
            $employees->lockForUpdate();
        }
        $employee = $employees->find($input->employeeId);

        if ($employee === null
            || ! $employee->is_active
            || $employee->department_id !== $input->departmentId
            || ! $employee->department->is_active) {
            throw ValidationException::withMessages(['employee_id' => 'Nama tidak ditemukan di departemen ini.']);
        }

        return $employee;
    }

    private function publicDate(OvertimeInput $input, CarbonImmutable $now): CarbonImmutable
    {
        if ($input->dateChoice === null) {
            throw ValidationException::withMessages(['overtime_date' => 'Pilih tanggal lembur.']);
        }

        $date = $this->dateRule->resolvePublic($input->dateChoice, $now);
        if ($input->date !== null && ! $input->date->isSameDay($date)) {
            throw ValidationException::withMessages(['overtime_date' => 'Tanggal lembur sudah berubah. Pilih tanggal lagi.']);
        }

        return $date;
    }

    private function validatedTimes(Employee $employee, OvertimeInput $input, CarbonImmutable $date, ?CarbonImmutable $completedBy = null): OvertimeTimes
    {
        $times = $this->calculator->calculate($date, $input->startTime, $input->endTime, $input->breakMinutes);

        if ($completedBy !== null && $times->endAt->greaterThan($completedBy)) {
            throw ValidationException::withMessages(['end_time' => 'Isi lembur setelah jam selesai.']);
        }

        $this->overlaps->assertNoConflict($employee->id, $times->startAt, $times->endAt);

        return $times;
    }

    private function lockOvertime(Overtime $overtime, OvertimeStatus $expected): Overtime
    {
        $locked = Overtime::query()->lockForUpdate()->findOrFail($overtime->id);

        if ($locked->status !== $expected) {
            throw new InvalidOvertimeTransition($locked->status);
        }

        return $locked;
    }

    /**
     * @throws ValidationException
     */
    private function panelDate(OvertimeInput $input, CarbonImmutable $now): CarbonImmutable
    {
        if ($input->date === null) {
            throw ValidationException::withMessages(['overtime_date' => 'Pilih tanggal lembur.']);
        }

        $this->dateRule->assertPanelDate($input->date, $now);

        return $input->date->startOfDay();
    }

    /**
     * R8, dihitung setelah spasi di awal/akhir dibuang.
     *
     * @throws ValidationException
     */
    private function validDescription(OvertimeInput $input): string
    {
        $description = $input->description();
        $length = mb_strlen($description);

        if ($length < self::MIN_TEXT) {
            throw ValidationException::withMessages(['description' => 'Tulis kegiatan lembur minimal 5 huruf.']);
        }

        if ($length > self::MAX_DESCRIPTION) {
            throw ValidationException::withMessages(['description' => 'Kegiatan lembur maksimal 500 huruf.']);
        }

        return $description;
    }

    /**
     * @throws ValidationException
     */
    private function validReason(string $reason): string
    {
        $reason = trim($reason);

        if (mb_strlen($reason) < self::MIN_TEXT) {
            throw ValidationException::withMessages(['reason' => 'Tulis alasan minimal 5 huruf.']);
        }

        if (mb_strlen($reason) > 500) {
            throw ValidationException::withMessages(['reason' => 'Alasan maksimal 500 karakter.']);
        }

        return $reason;
    }

    /**
     * Isi audit log before/after.
     *
     * @return array<string, mixed>
     */
    private function snapshot(Overtime $overtime): array
    {
        return [
            'number' => $overtime->number,
            'status' => $overtime->status->value,
            'overtime_date' => $overtime->overtime_date->toDateString(),
            'start_at' => $overtime->start_at->format('Y-m-d H:i'),
            'end_at' => $overtime->end_at->format('Y-m-d H:i'),
            'break_minutes' => $overtime->break_minutes,
            'total_minutes' => $overtime->total_minutes,
            'day_type' => $overtime->day_type->value,
            'description' => $overtime->description,
            'approved_by' => $overtime->approved_by,
        ];
    }
}

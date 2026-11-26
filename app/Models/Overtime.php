<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DayType;
use App\Enums\OvertimeSource;
use App\Enums\OvertimeStatus;
use Database\Factories\OvertimeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Pengajuan lembur. Tidak pernah dihapus; perubahan status lewat App\Services\OvertimeWorkflow (Fase 2).
 *
 * @property int $id
 * @property string $number
 * @property int $employee_id
 * @property int $department_id
 * @property Carbon $overtime_date
 * @property Carbon $start_at
 * @property Carbon $end_at
 * @property int $break_minutes
 * @property int $total_minutes
 * @property DayType $day_type
 * @property bool $exceeds_daily_limit
 * @property bool $exceeds_weekly_limit
 * @property string $description
 * @property OvertimeStatus $status
 * @property OvertimeSource $source
 * @property string|null $submitted_ip
 * @property string|null $submitted_user_agent
 * @property int|null $created_by
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property int|null $rejected_by
 * @property Carbon|null $rejected_at
 * @property string|null $reject_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'number', 'employee_id', 'department_id', 'overtime_date', 'start_at', 'end_at',
    'break_minutes', 'total_minutes', 'day_type', 'exceeds_daily_limit', 'exceeds_weekly_limit',
    'description', 'status', 'source', 'submitted_ip', 'submitted_user_agent',
    'created_by', 'approved_by', 'approved_at', 'rejected_by', 'rejected_at', 'reject_reason',
])]
class Overtime extends Model
{
    /** @use HasFactory<OvertimeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'overtime_date' => 'date',
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'break_minutes' => 'integer',
            'total_minutes' => 'integer',
            'exceeds_daily_limit' => 'boolean',
            'exceeds_weekly_limit' => 'boolean',
            'day_type' => DayType::class,
            'status' => OvertimeStatus::class,
            'source' => OvertimeSource::class,
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }
}

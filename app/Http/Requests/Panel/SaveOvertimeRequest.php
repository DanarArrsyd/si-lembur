<?php

declare(strict_types=1);

namespace App\Http\Requests\Panel;

use App\Models\Overtime;
use App\Models\User;
use App\Services\OvertimeCalculator;
use App\Services\OvertimeInput;
use Illuminate\Foundation\Http\FormRequest;

final class SaveOvertimeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();
        if (! $actor instanceof User) {
            return false;
        }

        // Rute edit sudah memakai Policy terhadap snapshot departemen.
        if ($this->routeIs('panel.overtimes.update') || $actor->isAdmin()) {
            return true;
        }

        $requestedDepartment = $this->integer('department_id', strict: true);

        return $actor->department_id !== null
            && ($requestedDepartment === 0 || $actor->canAccessDepartment($requestedDepartment));
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        $rules = [
            'overtime_date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'break_minutes' => ['required', 'integer', 'min:0', 'max:'.OvertimeCalculator::MAX_BREAK_MINUTES],
            'description' => ['required', 'string'],
        ];

        if ($this->routeIs('panel.overtimes.store')) {
            $rules['employee_id'] = ['required', 'integer', 'min:1'];
            $rules['department_id'] = [
                $this->user() instanceof User && $this->user()->isAdmin() ? 'required' : 'sometimes',
                'integer',
                'min:1',
            ];
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'department_id' => 'Pilih departemen.',
            'employee_id' => 'Pilih nama dari daftar.',
            'overtime_date.required' => 'Pilih tanggal lembur.',
            'overtime_date.date_format' => 'Tanggal lembur tidak valid.',
            'start_time.required' => 'Pilih jam mulai.',
            'end_time.required' => 'Pilih jam selesai.',
            'start_time.date_format' => 'Jam harus kelipatan 15 menit.',
            'end_time.date_format' => 'Jam harus kelipatan 15 menit.',
            'break_minutes' => 'Jam harus kelipatan 15 menit.',
            'break_minutes.max' => 'Istirahat maksimal 4 jam.',
            'description' => 'Tulis kegiatan lembur minimal 5 huruf.',
        ];
    }

    public function overtimeInput(User $actor, ?Overtime $overtime = null): OvertimeInput
    {
        return new OvertimeInput(
            employeeId: $overtime->employee_id ?? $this->integer('employee_id'),
            departmentId: $overtime->department_id ?? ($actor->isAdmin() ? $this->integer('department_id') : (int) $actor->department_id),
            dateChoice: null,
            date: $this->date('overtime_date', '!Y-m-d')?->toImmutable(),
            startTime: (string) $this->string('start_time'),
            endTime: (string) $this->string('end_time'),
            breakMinutes: $this->integer('break_minutes'),
            description: (string) $this->string('description'),
        );
    }
}

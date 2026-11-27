<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\DateChoice;
use App\Services\OvertimeCalculator;
use App\Services\OvertimeInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class PublicOvertimeRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->filled('website')) {
            Log::notice('Public overtime honeypot triggered.');

            abort(422, 'Pengajuan tidak bisa diproses. Muat ulang halaman lalu coba lagi.');
        }
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'department_id' => ['required', 'integer', 'min:1'],
            'employee_id' => ['required', 'integer', 'min:1'],
            'date_choice' => ['required', Rule::enum(DateChoice::class)],
            'overtime_date' => ['nullable', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'break_minutes' => ['required', 'integer', 'between:0,'.OvertimeCalculator::MAX_BREAK_MINUTES],
            'description' => ['required', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'department_id' => 'Pilih departemen.',
            'employee_id' => 'Pilih nama dari daftar.',
            'date_choice' => 'Pilih tanggal lembur.',
            'overtime_date' => 'Pilih tanggal lembur.',
            'start_time.required' => 'Pilih jam mulai.',
            'start_time.date_format' => 'Jam harus kelipatan 15 menit.',
            'end_time.required' => 'Pilih jam selesai.',
            'end_time.date_format' => 'Jam harus kelipatan 15 menit.',
            'break_minutes' => 'Pilih istirahat antara 0 menit dan 4 jam.',
            'description.required' => 'Tulis kegiatan lembur minimal 5 huruf.',
            'description.string' => 'Tulis kegiatan lembur.',
            'description.max' => 'Kegiatan lembur maksimal 500 huruf.',
        ];
    }

    public function overtimeInput(): OvertimeInput
    {
        return new OvertimeInput(
            employeeId: $this->integer('employee_id'),
            departmentId: $this->integer('department_id'),
            dateChoice: $this->enum('date_choice', DateChoice::class),
            date: $this->date('overtime_date', '!Y-m-d')?->toImmutable(),
            startTime: $this->string('start_time')->toString(),
            endTime: $this->string('end_time')->toString(),
            breakMinutes: $this->integer('break_minutes'),
            description: $this->string('description')->toString(),
        );
    }
}

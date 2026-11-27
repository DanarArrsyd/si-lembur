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
    protected $redirectRoute = 'home';

    protected function prepareForValidation(): void
    {
        $website = $this->input('website');

        if ($website !== null && $website !== '') {
            Log::notice('Public overtime honeypot triggered.', ['ip' => $this->ip()]);
            abort(422, 'Pengajuan tidak bisa diproses. Muat ulang halaman lalu coba lagi.');
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'department_id' => ['required', 'integer', 'min:1'],
            'employee_id' => ['required', 'integer', 'min:1'],
            'date_choice' => ['required', Rule::enum(DateChoice::class)],
            'overtime_date' => ['nullable', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'break_minutes' => ['required', 'integer', 'min:0', 'max:'.OvertimeCalculator::MAX_BREAK_MINUTES],
            'description' => ['required', 'string'],
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
            'end_time.required' => 'Pilih jam selesai.',
            'start_time.date_format' => 'Jam harus kelipatan 15 menit.',
            'end_time.date_format' => 'Jam harus kelipatan 15 menit.',
            'break_minutes' => 'Jam harus kelipatan 15 menit.',
            'break_minutes.max' => 'Istirahat maksimal 4 jam.',
            'description' => 'Tulis kegiatan lembur minimal 5 huruf.',
        ];
    }

    public function overtimeInput(): OvertimeInput
    {
        return new OvertimeInput(
            employeeId: $this->integer('employee_id'),
            departmentId: $this->integer('department_id'),
            dateChoice: $this->enum('date_choice', DateChoice::class),
            date: $this->date('overtime_date', '!Y-m-d')?->toImmutable(),
            startTime: (string) $this->string('start_time'),
            endTime: (string) $this->string('end_time'),
            breakMinutes: $this->integer('break_minutes'),
            description: (string) $this->string('description'),
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;

final class OvertimeReasonRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $reason = $this->input('reason');
        if (is_string($reason)) {
            $this->merge(['reason' => trim($reason)]);
        }
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:5', 'max:500']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'reason.required' => 'Tulis alasan minimal 5 huruf.',
            'reason.string' => 'Tulis alasan minimal 5 huruf.',
            'reason.min' => 'Tulis alasan minimal 5 huruf.',
            'reason.max' => 'Alasan maksimal 500 karakter.',
        ];
    }
}

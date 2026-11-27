<?php

declare(strict_types=1);

namespace App\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;

final class ApproveOvertimesRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'list', 'min:1', 'max:25'],
            'ids.*' => ['required', 'integer', 'min:1', 'distinct'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'ids.required' => 'Pilih minimal satu pengajuan.',
            'ids.min' => 'Pilih minimal satu pengajuan.',
            'ids.max' => 'Pilih maksimal 25 pengajuan.',
            'ids.array' => 'Periksa kembali pengajuan yang dipilih.',
            'ids.list' => 'Periksa kembali pengajuan yang dipilih.',
            'ids.*' => 'Periksa kembali pengajuan yang dipilih.',
        ];
    }

    /** @return list<int> */
    public function overtimeIds(): array
    {
        /** @var list<int|string> $ids */
        $ids = $this->validated('ids');

        return array_map(intval(...), $ids);
    }
}

<?php

namespace App\Http\Requests\Api\Report;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'TenBaoCao' => ['sometimes', 'string', 'max:255'],
            'LoaiBaoCao' => ['sometimes', 'nullable', 'string', 'max:100'],
            'NguoiTao' => ['sometimes', 'nullable', 'string', 'max:255'],
            'NoiDung' => ['sometimes', 'nullable', 'string'],
            'TrangThai' => ['sometimes', 'nullable', 'string', 'max:50'],
            'NgayTao' => ['sometimes', 'nullable', 'date'],
        ];
    }
}

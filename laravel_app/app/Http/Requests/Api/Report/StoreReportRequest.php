<?php

namespace App\Http\Requests\Api\Report;

use Illuminate\Foundation\Http\FormRequest;

class StoreReportRequest extends FormRequest
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
            'TenBaoCao' => ['required', 'string', 'max:255'],
            'LoaiBaoCao' => ['nullable', 'string', 'max:100'],
            'NguoiTao' => ['nullable', 'string', 'max:255'],
            'NoiDung' => ['nullable', 'string'],
            'TrangThai' => ['nullable', 'string', 'max:50'],
            'NgayTao' => ['nullable', 'date'],
        ];
    }
}

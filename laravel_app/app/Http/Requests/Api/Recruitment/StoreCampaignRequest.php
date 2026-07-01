<?php

namespace App\Http\Requests\Api\Recruitment;

use Illuminate\Foundation\Http\FormRequest;

class StoreCampaignRequest extends FormRequest
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
            'TenDotTuyenDung' => ['required', 'string', 'max:255'],
            'ViTriTuyenDung' => ['required', 'string', 'max:255'],
            'SoLuong' => ['nullable', 'integer', 'min:1'],
            'TuNgay' => ['nullable', 'date'],
            'DenNgay' => ['nullable', 'date'],
            'TrangThai' => ['nullable', 'string', 'max:50'],
            'MoTa' => ['nullable', 'string'],
        ];
    }
}

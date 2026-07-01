<?php

namespace App\Http\Requests\Api\Recruitment;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCampaignRequest extends FormRequest
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
            'TenDotTuyenDung' => ['sometimes', 'string', 'max:255'],
            'ViTriTuyenDung' => ['sometimes', 'string', 'max:255'],
            'SoLuong' => ['sometimes', 'integer', 'min:1'],
            'TuNgay' => ['sometimes', 'date'],
            'DenNgay' => ['sometimes', 'date'],
            'TrangThai' => ['sometimes', 'string', 'max:50'],
            'MoTa' => ['sometimes', 'nullable', 'string'],
        ];
    }
}

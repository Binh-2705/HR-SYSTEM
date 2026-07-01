<?php

namespace App\Http\Requests\Api\Training;

use Illuminate\Foundation\Http\FormRequest;

class UpdateParticipantRequest extends FormRequest
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
            'MaNV' => ['sometimes', 'integer', 'min:1'],
            'KetQua' => ['sometimes', 'nullable', 'string', 'max:255'],
            'DanhGia' => ['sometimes', 'nullable', 'string'],
            'GhiChu' => ['sometimes', 'nullable', 'string'],
        ];
    }
}

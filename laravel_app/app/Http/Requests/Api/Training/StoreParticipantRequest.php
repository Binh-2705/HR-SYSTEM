<?php

namespace App\Http\Requests\Api\Training;

use Illuminate\Foundation\Http\FormRequest;

class StoreParticipantRequest extends FormRequest
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
            'MaNV' => ['required', 'integer', 'min:1'],
            'KetQua' => ['nullable', 'string', 'max:255'],
            'DanhGia' => ['nullable', 'string'],
            'GhiChu' => ['nullable', 'string'],
        ];
    }
}

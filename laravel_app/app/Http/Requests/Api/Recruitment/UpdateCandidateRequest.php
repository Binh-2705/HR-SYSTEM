<?php

namespace App\Http\Requests\Api\Recruitment;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCandidateRequest extends FormRequest
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
            'HoTen' => ['sometimes', 'string', 'max:255'],
            'Email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'DienThoai' => ['sometimes', 'nullable', 'string', 'max:30'],
            'NgaySinh' => ['sometimes', 'nullable', 'date'],
            'GioiTinh' => ['sometimes', 'nullable', 'string', 'max:50'],
            'DiaChi' => ['sometimes', 'nullable', 'string', 'max:500'],
            'KinhNghiem' => ['sometimes', 'nullable', 'string'],
            'KyNang' => ['sometimes', 'nullable', 'string'],
            'TrangThai' => ['sometimes', 'nullable', 'string', 'max:50'],
        ];
    }
}

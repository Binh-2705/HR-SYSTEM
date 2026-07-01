<?php

namespace App\Http\Requests\Api\Recruitment;

use Illuminate\Foundation\Http\FormRequest;

class StoreCandidateRequest extends FormRequest
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
            'HoTen' => ['required', 'string', 'max:255'],
            'Email' => ['nullable', 'email', 'max:255'],
            'DienThoai' => ['nullable', 'string', 'max:30'],
            'NgaySinh' => ['nullable', 'date'],
            'GioiTinh' => ['nullable', 'string', 'max:50'],
            'DiaChi' => ['nullable', 'string', 'max:500'],
            'KinhNghiem' => ['nullable', 'string'],
            'KyNang' => ['nullable', 'string'],
            'TrangThai' => ['nullable', 'string', 'max:50'],
        ];
    }
}

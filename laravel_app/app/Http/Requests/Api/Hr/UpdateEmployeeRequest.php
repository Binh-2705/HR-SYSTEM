<?php

namespace App\Http\Requests\Api\Hr;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeRequest extends FormRequest
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
            'GioiTinh' => ['nullable', 'string', 'max:50'],
            'NgaySinh' => ['nullable', 'date'],
            'Email' => ['nullable', 'email', 'max:255'],
            'DienThoai' => ['nullable', 'string', 'max:30'],
            'TrangThai' => ['required', 'string', 'max:50'],
            'MaBac' => ['nullable', 'integer', 'min:1'],
        ];
    }
}

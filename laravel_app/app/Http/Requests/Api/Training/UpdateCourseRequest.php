<?php

namespace App\Http\Requests\Api\Training;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCourseRequest extends FormRequest
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
            'TenKhoaDaoTao' => ['sometimes', 'string', 'max:255'],
            'TuNgay' => ['sometimes', 'nullable', 'date'],
            'DenNgay' => ['sometimes', 'nullable', 'date'],
            'NoiDung' => ['sometimes', 'nullable', 'string'],
            'DonViToChuc' => ['sometimes', 'nullable', 'string', 'max:255'],
            'TrangThai' => ['sometimes', 'nullable', 'string', 'max:50'],
        ];
    }
}

<?php

namespace App\Http\Requests\Api\Training;

use Illuminate\Foundation\Http\FormRequest;

class StoreCourseRequest extends FormRequest
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
            'TenKhoaDaoTao' => ['required', 'string', 'max:255'],
            'TuNgay' => ['nullable', 'date'],
            'DenNgay' => ['nullable', 'date'],
            'NoiDung' => ['nullable', 'string'],
            'DonViToChuc' => ['nullable', 'string', 'max:255'],
            'TrangThai' => ['nullable', 'string', 'max:50'],
        ];
    }
}

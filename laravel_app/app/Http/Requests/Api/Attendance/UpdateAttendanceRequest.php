<?php

namespace App\Http\Requests\Api\Attendance;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAttendanceRequest extends FormRequest
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
            'Ngay' => ['sometimes', 'date'],
            'GioVao' => ['sometimes', 'nullable', 'date_format:H:i:s'],
            'GioRa' => ['sometimes', 'nullable', 'date_format:H:i:s'],
            'TrangThai' => ['sometimes', 'string', 'max:50'],
            'GhiChu' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}

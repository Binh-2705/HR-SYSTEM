<?php

namespace App\Http\Requests\Api\Attendance;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttendanceRequest extends FormRequest
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
            'Ngay' => ['required', 'date'],
            'GioVao' => ['nullable', 'date_format:H:i:s'],
            'GioRa' => ['nullable', 'date_format:H:i:s'],
            'TrangThai' => ['required', 'string', 'max:50'],
            'GhiChu' => ['nullable', 'string', 'max:1000'],
        ];
    }
}

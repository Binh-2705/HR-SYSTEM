<?php

namespace App\Http\Requests\Api\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class StorePayrollRequest extends FormRequest
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
            'Thang' => ['required', 'integer', 'between:1,12'],
            'Nam' => ['required', 'integer', 'min:2000', 'max:2100'],
            'LuongCoSo' => ['nullable', 'numeric', 'min:0'],
            'HeSoLuong' => ['nullable', 'numeric', 'min:0'],
            'HeSoChucVu' => ['nullable', 'numeric', 'min:0'],
            'PhuCap' => ['nullable', 'numeric', 'min:0'],
            'Thuong' => ['nullable', 'numeric', 'min:0'],
            'Phat' => ['nullable', 'numeric', 'min:0'],
            'BaoHiem' => ['nullable', 'numeric', 'min:0'],
            'TongLuong' => ['nullable', 'numeric'],
            'TrangThai' => ['nullable', 'string', 'max:50'],
            'NgayTinh' => ['nullable', 'date'],
        ];
    }
}

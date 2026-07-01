<?php

namespace App\Http\Requests\Api\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePayrollRequest extends FormRequest
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
            'Thang' => ['sometimes', 'integer', 'between:1,12'],
            'Nam' => ['sometimes', 'integer', 'min:2000', 'max:2100'],
            'LuongCoSo' => ['sometimes', 'numeric', 'min:0'],
            'HeSoLuong' => ['sometimes', 'numeric', 'min:0'],
            'HeSoChucVu' => ['sometimes', 'numeric', 'min:0'],
            'PhuCap' => ['sometimes', 'numeric', 'min:0'],
            'Thuong' => ['sometimes', 'numeric', 'min:0'],
            'Phat' => ['sometimes', 'numeric', 'min:0'],
            'BaoHiem' => ['sometimes', 'numeric', 'min:0'],
            'TongLuong' => ['sometimes', 'numeric'],
            'TrangThai' => ['sometimes', 'string', 'max:50'],
            'NgayTinh' => ['sometimes', 'date'],
        ];
    }
}

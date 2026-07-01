<?php

namespace App\Http\Resources\Api\Payroll;

use Illuminate\Http\Resources\Json\JsonResource;

class PayrollResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $row = (array) $this->resource;

        return [
            'MaBL' => $row['MaBL'] ?? null,
            'MaNV' => $row['MaNV'] ?? null,
            'HoTen' => $row['HoTen'] ?? null,
            'Thang' => $row['Thang'] ?? null,
            'Nam' => $row['Nam'] ?? null,
            'LuongCoSo' => $row['LuongCoSo'] ?? null,
            'HeSoLuong' => $row['HeSoLuong'] ?? null,
            'HeSoChucVu' => $row['HeSoChucVu'] ?? null,
            'PhuCap' => $row['PhuCap'] ?? null,
            'Thuong' => $row['Thuong'] ?? null,
            'Phat' => $row['Phat'] ?? null,
            'BaoHiem' => $row['BaoHiem'] ?? null,
            'TongLuong' => $row['TongLuong'] ?? null,
            'TrangThai' => $row['TrangThai'] ?? null,
            'NgayTinh' => $row['NgayTinh'] ?? null,
            '__resource_id' => isset($row['MaBL']) ? (string) $row['MaBL'] : null,
        ];
    }
}

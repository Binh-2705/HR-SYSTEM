<?php

namespace App\Http\Resources\Api\Hr;

use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $row = (array) $this->resource;

        return [
            'MaNV' => $row['MaNV'] ?? null,
            'HoTen' => $row['HoTen'] ?? null,
            'GioiTinh' => $row['GioiTinh'] ?? null,
            'NgaySinh' => $row['NgaySinh'] ?? null,
            'Email' => $row['Email'] ?? null,
            'DienThoai' => $row['DienThoai'] ?? null,
            'TrangThai' => $row['TrangThai'] ?? null,
            'MaBac' => $row['MaBac'] ?? null,
            'TenBac' => $row['TenBac'] ?? null,
            'TenNgach' => $row['TenNgach'] ?? null,
            'DiaChi' => $row['DiaChi'] ?? null,
            'NgayVaoLam' => $row['NgayVaoLam'] ?? null,
            'CurrentMaPB' => $row['CurrentMaPB'] ?? null,
            'TenPB' => $row['TenPB'] ?? null,
            'CurrentMaCV' => $row['CurrentMaCV'] ?? null,
            'TenCV' => $row['TenCV'] ?? null,
            '__resource_id' => isset($row['MaNV']) ? (string) $row['MaNV'] : null,
        ];
    }
}

<?php

namespace App\Http\Resources\Api\Training;

use Illuminate\Http\Resources\Json\JsonResource;

class CourseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $row = (array) $this->resource;

        return [
            'MaKDT' => $row['MaKDT'] ?? null,
            'TenKhoaDaoTao' => $row['TenKhoaDaoTao'] ?? null,
            'TuNgay' => $row['TuNgay'] ?? null,
            'DenNgay' => $row['DenNgay'] ?? null,
            'NoiDung' => $row['NoiDung'] ?? null,
            'DonViToChuc' => $row['DonViToChuc'] ?? null,
            'TrangThai' => $row['TrangThai'] ?? null,
            'SoHocVien' => $row['SoHocVien'] ?? null,
            '__resource_id' => isset($row['MaKDT']) ? (string) $row['MaKDT'] : null,
        ];
    }
}

<?php

namespace App\Http\Resources\Api\Attendance;

use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $row = (array) $this->resource;

        return [
            'MaCC' => $row['MaCC'] ?? null,
            'MaNV' => $row['MaNV'] ?? null,
            'HoTen' => $row['HoTen'] ?? null,
            'TenPB' => $row['TenPB'] ?? null,
            'Ngay' => $row['Ngay'] ?? null,
            'GioVao' => $row['GioVao'] ?? null,
            'GioRa' => $row['GioRa'] ?? null,
            'TrangThai' => $row['TrangThai'] ?? null,
            'GhiChu' => $row['GhiChu'] ?? null,
            '__resource_id' => isset($row['MaCC']) ? (string) $row['MaCC'] : null,
        ];
    }
}

<?php

namespace App\Http\Resources\Api\Recruitment;

use Illuminate\Http\Resources\Json\JsonResource;

class CandidateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $row = (array) $this->resource;

        return [
            'MaUV' => $row['MaUV'] ?? null,
            'HoTen' => $row['HoTen'] ?? null,
            'Email' => $row['Email'] ?? null,
            'DienThoai' => $row['DienThoai'] ?? null,
            'NgaySinh' => $row['NgaySinh'] ?? null,
            'GioiTinh' => $row['GioiTinh'] ?? null,
            'DiaChi' => $row['DiaChi'] ?? null,
            'KinhNghiem' => $row['KinhNghiem'] ?? null,
            'KyNang' => $row['KyNang'] ?? null,
            'TrangThai' => $row['TrangThai'] ?? null,
            '__resource_id' => isset($row['MaUV']) ? (string) $row['MaUV'] : null,
        ];
    }
}

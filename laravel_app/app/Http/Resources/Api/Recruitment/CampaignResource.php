<?php

namespace App\Http\Resources\Api\Recruitment;

use Illuminate\Http\Resources\Json\JsonResource;

class CampaignResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $row = (array) $this->resource;

        return [
            'MaDTD' => $row['MaDTD'] ?? null,
            'TenDotTuyenDung' => $row['TenDotTuyenDung'] ?? null,
            'ViTriTuyenDung' => $row['ViTriTuyenDung'] ?? null,
            'SoLuong' => $row['SoLuong'] ?? null,
            'TuNgay' => $row['TuNgay'] ?? null,
            'DenNgay' => $row['DenNgay'] ?? null,
            'TrangThai' => $row['TrangThai'] ?? null,
            'MoTa' => $row['MoTa'] ?? null,
            'SoHoSo' => $row['SoHoSo'] ?? null,
            '__resource_id' => isset($row['MaDTD']) ? (string) $row['MaDTD'] : null,
        ];
    }
}

<?php

namespace App\Http\Resources\Api\Report;

use Illuminate\Http\Resources\Json\JsonResource;

class ReportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $row = (array) $this->resource;

        return [
            'MaBC' => $row['MaBC'] ?? null,
            'TenBaoCao' => $row['TenBaoCao'] ?? null,
            'LoaiBaoCao' => $row['LoaiBaoCao'] ?? null,
            'NguoiTao' => $row['NguoiTao'] ?? null,
            'NoiDung' => $row['NoiDung'] ?? null,
            'TrangThai' => $row['TrangThai'] ?? null,
            'NgayTao' => $row['NgayTao'] ?? null,
            '__resource_id' => isset($row['MaBC']) ? (string) $row['MaBC'] : null,
        ];
    }
}

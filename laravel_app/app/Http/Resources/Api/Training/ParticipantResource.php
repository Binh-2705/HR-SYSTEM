<?php

namespace App\Http\Resources\Api\Training;

use Illuminate\Http\Resources\Json\JsonResource;

class ParticipantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $row = (array) $this->resource;

        return [
            'MaTGDT' => $row['MaTGDT'] ?? null,
            'MaKDT' => $row['MaKDT'] ?? null,
            'MaNV' => $row['MaNV'] ?? null,
            'HoTen' => $row['HoTen'] ?? null,
            'Email' => $row['Email'] ?? null,
            'KetQua' => $row['KetQua'] ?? null,
            'DanhGia' => $row['DanhGia'] ?? null,
            'GhiChu' => $row['GhiChu'] ?? null,
            '__resource_id' => isset($row['MaTGDT']) ? (string) $row['MaTGDT'] : null,
        ];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class RecruitmentApplication extends Model
{
    protected $table = 'hosoungtuyen';

    protected $primaryKey = 'MaHS';

    public $timestamps = false;

    protected $guarded = [];

    public function getConnectionName(): ?string
    {
        return (string) config('service_registry.services.recruitment.connection', config('database.default'));
    }

    public function candidate()
    {
        return $this->belongsTo(RecruitmentCandidate::class, 'MaUV', 'MaUV');
    }

    public function campaign()
    {
        return $this->belongsTo(RecruitmentCampaign::class, 'MaDTD', 'MaDTD');
    }

    public function scopeWithCandidateCampaignContext(Builder $query): Builder
    {
        return $query
            ->from('hosoungtuyen as hs')
            ->join('ungvien as uv', 'uv.MaUV', '=', 'hs.MaUV')
            ->join('dottuyendung as dt', 'dt.MaDTD', '=', 'hs.MaDTD')
            ->select([
                'hs.*',
                'uv.HoTen',
                'uv.Email',
                'uv.DienThoai',
                'uv.FileCV',
                'uv.DiemCV',
                'dt.TenDotTuyenDung',
                'dt.ViTriTuyenDung',
            ]);
    }

    public function scopeApplyFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when(!empty($filters['q']), function (Builder $q) use ($filters) {
                $keyword = trim((string) $filters['q']);
                $q->where(fn ($inner) => $inner
                    ->where('uv.HoTen', 'like', "%{$keyword}%")
                    ->orWhere('uv.Email', 'like', "%{$keyword}%")
                    ->orWhere('uv.DienThoai', 'like', "%{$keyword}%"));
            })
            ->when(!empty($filters['status']), fn (Builder $q) => $q->where('hs.TrangThai', $filters['status']));
    }

    public function scopeSortDefault(Builder $query): Builder
    {
        return $query->orderByDesc('hs.MaHS');
    }
}

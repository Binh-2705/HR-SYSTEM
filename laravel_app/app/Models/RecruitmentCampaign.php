<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class RecruitmentCampaign extends Model
{
    protected $table = 'dottuyendung';

    protected $primaryKey = 'MaDTD';

    public $timestamps = false;

    protected $guarded = [];

    public function getConnectionName(): ?string
    {
        return (string) config('service_registry.services.recruitment.connection', config('database.default'));
    }

    public function applications()
    {
        return $this->hasMany(RecruitmentApplication::class, 'MaDTD', 'MaDTD');
    }

    public function scopeWithApplicationCount(Builder $query): Builder
    {
        return $query
            ->from('dottuyendung as dt')
            ->leftJoin('hosoungtuyen as hs', 'hs.MaDTD', '=', 'dt.MaDTD')
            ->select([
                'dt.MaDTD',
                'dt.TenDotTuyenDung',
                'dt.ViTriTuyenDung',
                'dt.SoLuong',
                'dt.TuNgay',
                'dt.DenNgay',
                'dt.TrangThai',
                'dt.MoTa',
                DB::raw('COUNT(hs.MaHS) as SoHoSo'),
            ])
            ->groupBy(
                'dt.MaDTD',
                'dt.TenDotTuyenDung',
                'dt.ViTriTuyenDung',
                'dt.SoLuong',
                'dt.TuNgay',
                'dt.DenNgay',
                'dt.TrangThai',
                'dt.MoTa'
            );
    }

    public function scopeApplyFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when(!empty($filters['q']), function (Builder $q) use ($filters) {
                $keyword = trim((string) $filters['q']);
                $q->where(fn ($inner) => $inner
                    ->where('dt.TenDotTuyenDung', 'like', "%{$keyword}%")
                    ->orWhere('dt.ViTriTuyenDung', 'like', "%{$keyword}%"));
            })
            ->when(!empty($filters['status']), fn (Builder $q) => $q->where('dt.TrangThai', $filters['status']));
    }

    public function scopeSortDefault(Builder $query): Builder
    {
        return $query->orderByDesc('dt.MaDTD');
    }
}

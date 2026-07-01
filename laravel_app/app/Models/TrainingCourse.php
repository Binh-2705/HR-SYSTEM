<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class TrainingCourse extends Model
{
    protected $table = 'khoadaotao';

    protected $primaryKey = 'MaKDT';

    public $timestamps = false;

    protected $guarded = [];

    public function getConnectionName(): ?string
    {
        return (string) config('service_registry.services.training.connection', config('database.default'));
    }

    public function participants()
    {
        return $this->hasMany(TrainingParticipant::class, 'MaKDT', 'MaKDT');
    }

    public function scopeWithParticipantCount(Builder $query): Builder
    {
        return $query
            ->from('khoadaotao as kdt')
            ->leftJoin('thamgiadaotao as tg', 'tg.MaKDT', '=', 'kdt.MaKDT')
            ->select([
                'kdt.MaKDT',
                'kdt.TenKhoaDaoTao',
                'kdt.TuNgay',
                'kdt.DenNgay',
                'kdt.NoiDung',
                'kdt.DonViToChuc',
                'kdt.TrangThai',
                DB::raw('COUNT(tg.MaTGDT) as SoHocVien'),
            ])
            ->groupBy(
                'kdt.MaKDT',
                'kdt.TenKhoaDaoTao',
                'kdt.TuNgay',
                'kdt.DenNgay',
                'kdt.NoiDung',
                'kdt.DonViToChuc',
                'kdt.TrangThai'
            );
    }

    public function scopeApplyFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when(!empty($filters['q']), function (Builder $q) use ($filters) {
                $keyword = trim((string) $filters['q']);
                $q->where(fn ($inner) => $inner
                    ->where('kdt.TenKhoaDaoTao', 'like', "%{$keyword}%")
                    ->orWhere('kdt.DonViToChuc', 'like', "%{$keyword}%"));
            })
            ->when(!empty($filters['status']), fn (Builder $q) => $q->where('kdt.TrangThai', $filters['status']));
    }

    public function scopeSortDefault(Builder $query): Builder
    {
        return $query->orderByDesc('kdt.MaKDT');
    }
}

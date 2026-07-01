<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class RecruitmentCandidate extends Model
{
    protected $table = 'ungvien';

    protected $primaryKey = 'MaUV';

    public $timestamps = false;

    protected $guarded = [];

    public function getConnectionName(): ?string
    {
        return (string) config('service_registry.services.recruitment.connection', config('database.default'));
    }

    public function applications()
    {
        return $this->hasMany(RecruitmentApplication::class, 'MaUV', 'MaUV');
    }

    public function scopeApplyFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when(!empty($filters['q']), function (Builder $q) use ($filters) {
                $keyword = trim((string) $filters['q']);
                $q->where(fn ($inner) => $inner
                    ->where('HoTen', 'like', "%{$keyword}%")
                    ->orWhere('Email', 'like', "%{$keyword}%")
                    ->orWhere('DienThoai', 'like', "%{$keyword}%"));
            })
            ->when(!empty($filters['score']), fn (Builder $q) => $q->where('DiemCV', '>=', (int) $filters['score']));
    }

    public function scopeSortDefault(Builder $query): Builder
    {
        return $query->orderByDesc('MaUV');
    }
}

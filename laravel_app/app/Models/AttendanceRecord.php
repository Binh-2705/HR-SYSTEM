<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AttendanceRecord extends Model
{
    protected $table = 'chamcong';

    protected $primaryKey = 'MaCC';

    public $timestamps = false;

    protected $guarded = [];

    public function getConnectionName(): ?string
    {
        return (string) config('service_registry.services.attendance.connection', config('database.default'));
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'MaNV', 'MaNV');
    }

    public function scopeWithEmployeeContext(Builder $query): Builder
    {
        return $query
            ->from('chamcong as cc')
            ->join('nhanvien as nv', 'nv.MaNV', '=', 'cc.MaNV')
            ->leftJoin('hosonhanvien as hs', 'hs.MaNV', '=', 'nv.MaNV')
            ->leftJoin('phongban as pb', 'pb.MaPB', '=', 'hs.MaPB')
            ->select([
                'cc.MaCC',
                'cc.MaNV',
                'cc.Ngay',
                'cc.GioVao',
                'cc.GioRa',
                'cc.TrangThai',
                'cc.GhiChu',
                'nv.HoTen',
                'pb.TenPB',
            ]);
    }

    public function scopeApplyFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when(!empty($filters['ma_nv']), fn (Builder $q) => $q->where('cc.MaNV', (int) $filters['ma_nv']))
            ->when(!empty($filters['q']), function (Builder $q) use ($filters) {
                $keyword = trim((string) $filters['q']);
                $q->where(fn ($inner) => $inner
                    ->where('nv.HoTen', 'like', "%{$keyword}%")
                    ->orWhere('cc.MaNV', 'like', "%{$keyword}%"));
            })
            ->when(!empty($filters['status']), fn (Builder $q) => $q->where('cc.TrangThai', $filters['status']))
            ->when(!empty($filters['date']), fn (Builder $q) => $q->whereDate('cc.Ngay', $filters['date']));
    }

    public function scopeSortDefault(Builder $query): Builder
    {
        return $query->orderByDesc('cc.Ngay')->orderBy('cc.MaNV');
    }
}

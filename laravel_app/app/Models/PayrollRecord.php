<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PayrollRecord extends Model
{
    protected $table = 'bangluong';

    protected $primaryKey = 'MaBL';

    public $timestamps = false;

    protected $guarded = [];

    public function getConnectionName(): ?string
    {
        return (string) config('service_registry.services.payroll.connection', config('database.default'));
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'MaNV', 'MaNV');
    }

    public function scopeWithEmployeeContext(Builder $query): Builder
    {
        return $query
            ->from('bangluong as bl')
            ->join('nhanvien as nv', 'nv.MaNV', '=', 'bl.MaNV')
            ->select([
                'bl.MaBL',
                'bl.MaNV',
                'bl.Thang',
                'bl.Nam',
                'bl.LuongCoSo',
                'bl.HeSoLuong',
                'bl.HeSoChucVu',
                'bl.PhuCap',
                'bl.Thuong',
                'bl.Phat',
                'bl.BaoHiem',
                'bl.TongLuong',
                'bl.TrangThai',
                'bl.NgayTinh',
                'nv.HoTen',
            ]);
    }

    public function scopeApplyFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when(!empty($filters['ma_nv']), fn (Builder $q) => $q->where('bl.MaNV', (int) $filters['ma_nv']))
            ->when(!empty($filters['q']), function (Builder $q) use ($filters) {
                $keyword = trim((string) $filters['q']);
                $q->where(fn ($inner) => $inner
                    ->where('nv.HoTen', 'like', "%{$keyword}%")
                    ->orWhere('bl.MaNV', 'like', "%{$keyword}%"));
            })
            ->when(!empty($filters['month']), fn (Builder $q) => $q->where('bl.Thang', (int) $filters['month']))
            ->when(!empty($filters['year']), fn (Builder $q) => $q->where('bl.Nam', (int) $filters['year']))
            ->when(!empty($filters['status']), fn (Builder $q) => $q->where('bl.TrangThai', $filters['status']));
    }

    public function scopeSortDefault(Builder $query): Builder
    {
        return $query->orderByDesc('bl.Nam')->orderByDesc('bl.Thang')->orderBy('bl.MaNV');
    }
}

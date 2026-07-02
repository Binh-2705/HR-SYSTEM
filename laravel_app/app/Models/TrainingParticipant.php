<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
//binh oi cap quyen cho toi
class TrainingParticipant extends Model
{
    protected $table = 'thamgiadaotao';

    protected $primaryKey = 'MaTGDT';

    public $timestamps = false;

    protected $guarded = [];

    public function getConnectionName(): ?string
    {
        return (string) config('service_registry.services.training.connection', config('database.default'));
    }

    public function course()
    {
        return $this->belongsTo(TrainingCourse::class, 'MaKDT', 'MaKDT');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'MaNV', 'MaNV');
    }

    public function scopeWithEmployeeContext(Builder $query): Builder
    {
        return $query
            ->from('thamgiadaotao as tg')
            ->join('nhanvien as nv', 'nv.MaNV', '=', 'tg.MaNV')
            ->select('tg.*', 'nv.HoTen', 'nv.Email');
    }
}

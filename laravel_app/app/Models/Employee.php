<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $table = 'nhanvien';

    protected $primaryKey = 'MaNV';

    public $timestamps = false;

    protected $guarded = [];
}

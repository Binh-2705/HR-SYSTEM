<?php

namespace Tests\Feature;

use App\Services\AttendanceService;
use App\Services\PayrollService;
use App\Services\PermissionService;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class AttendancePayrollModulesTest extends TestCase
{
    public function test_attendance_index_renders_for_authorized_session(): void
    {
        $this->mock(PermissionService::class, function ($mock) {
            $mock->shouldReceive('hasPermission', 'hasPermissionFromCache')->andReturnTrue();
        });

        $this->mock(AttendanceService::class, function ($mock) {
            $mock->shouldReceive('paginate')->andReturn(new LengthAwarePaginator([
                (object) [
                    'MaCC' => 1,
                    'MaNV' => 1,
                    'HoTen' => 'Nguyen Van A',
                    'TenPB' => 'Nhan Su',
                    'Ngay' => '2026-04-01',
                    'GioVao' => '08:00',
                    'GioRa' => '17:00',
                    'TrangThai' => 'Di lam',
                ],
            ], 1, 15));
        });

        $response = $this->withSession(['MaTK' => 3])->get('/chamcong');

        $response->assertOk()->assertSee('Nguyen Van A');
    }

    public function test_payroll_index_renders_for_authorized_session(): void
    {
        $this->mock(PermissionService::class, function ($mock) {
            $mock->shouldReceive('hasPermission', 'hasPermissionFromCache')->andReturnTrue();
        });

        $this->mock(PayrollService::class, function ($mock) {
            $mock->shouldReceive('paginate')->andReturn(new LengthAwarePaginator([
                (object) [
                    'MaBL' => 1,
                    'MaNV' => 1,
                    'HoTen' => 'Nguyen Van A',
                    'Thang' => 4,
                    'Nam' => 2026,
                    'TongLuong' => 10000000,
                    'TrangThai' => 'Chưa chốt',
                ],
            ], 1, 15));
        });

        $response = $this->withSession(['MaTK' => 3])->get('/luong');

        $response->assertOk()->assertSee('Nguyen Van A');
    }
}
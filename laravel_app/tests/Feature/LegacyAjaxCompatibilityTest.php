<?php

namespace Tests\Feature;

use App\Services\AttendanceService;
use App\Services\HrEmployeeService;
use App\Services\PayrollService;
use App\Services\PermissionService;
use App\Services\RecruitmentService;
use Tests\TestCase;

class LegacyAjaxCompatibilityTest extends TestCase
{
    public function test_salary_grades_by_band_returns_legacy_option_markup(): void
    {
        $this->mock(PermissionService::class, fn ($mock) => $mock->shouldReceive('hasPermission', 'hasPermissionFromCache')->andReturnTrue());
        $this->mock(HrEmployeeService::class, function ($mock) {
            $mock->shouldReceive('salaryGradesByBand')->once()->with('2')->andReturn([
                ['MaBac' => 5, 'TenBac' => 'Bac 5', 'HeSoLuong' => '3.66'],
            ]);
        });

        $this->withSession(['MaTK' => 3])
            ->get('/employees/salary-grades-by-band?ma_ngach=2')
            ->assertOk()
            ->assertSee('Bac 5', false);
    }

    public function test_worked_days_endpoint_returns_legacy_json_shape(): void
    {
        $this->mock(PermissionService::class, fn ($mock) => $mock->shouldReceive('hasPermission', 'hasPermissionFromCache')->andReturnTrue());
        $this->mock(AttendanceService::class, function ($mock) {
            $mock->shouldReceive('workedDaysByMonth')->once()->with(7, 4, null)->andReturn([
                'SoNgayLam' => 21,
                'GioOT' => 4,
                'Thang' => 4,
                'Nam' => 2026,
            ]);
        });

        $this->withSession(['MaTK' => 3])
            ->getJson('/attendance/worked-days?manv=7&thang=4')
            ->assertOk()
            ->assertJson(['SoNgayLam' => 21, 'GioOT' => 4]);
    }

    public function test_run_monthly_payroll_returns_success_json(): void
    {
        $this->mock(PermissionService::class, fn ($mock) => $mock->shouldReceive('hasPermission', 'hasPermissionFromCache')->andReturnTrue());
        $this->mock(PayrollService::class, function ($mock) {
            $mock->shouldReceive('processMonthlyPayroll')->once()->with(4, 2026)->andReturn(12);
        });

        $this->withSession(['MaTK' => 3])
            ->postJson('/payroll/run-monthly', ['thang' => 4, 'nam' => 2026])
            ->assertOk()
            ->assertJson(['ok' => true, 'processed' => 12]);
    }

    public function test_recruitment_kanban_update_returns_legacy_ok_text(): void
    {
        $this->mock(PermissionService::class, fn ($mock) => $mock->shouldReceive('hasPermission', 'hasPermissionFromCache')->andReturnTrue());
        $this->mock(RecruitmentService::class, function ($mock) {
            $mock->shouldReceive('updateKanban')->once()->with(18, [
                'TrangThai' => 'Phỏng vấn',
                'GhiChu' => null,
            ])->andReturnNull();
        });

        $this->withSession(['MaTK' => 3])
            ->post('/recruitment/applications/kanban-status', ['MaHS' => 18, 'TrangThai' => 'Phỏng vấn'])
            ->assertOk()
            ->assertSee('ok');
    }
}
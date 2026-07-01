<?php

namespace Tests\Feature;

use App\Services\AppAuditLogService;
use App\Services\PermissionService;
use App\Services\ReportService;
use Tests\TestCase;

class ExportImportCompatibilityTest extends TestCase
{
    public function test_department_export_excel_legacy_route_is_removed(): void
    {
        $response = $this->withSession(['MaTK' => 3])->get('/phongban/export-excel');
        $response->assertStatus(404);
    }

    public function test_department_import_csv_legacy_route_is_removed(): void
    {
        $this->withSession(['MaTK' => 3])
            ->post('/phongban/import-csv', ['filecsv' => 'dummy'])
            ->assertStatus(404);
    }

    public function test_report_export_json_returns_payload(): void
    {
        $this->mock(PermissionService::class, fn ($mock) => $mock->shouldReceive('hasPermission', 'hasPermissionFromCache')->andReturnTrue());
        $this->mock(ReportService::class, function ($mock) {
            $mock->shouldReceive('exportRows')->once()->with([])->andReturn([
                ['MaBC' => 5, 'TenBaoCao' => 'Tong hop', 'LoaiBaoCao' => 'Nhân sự', 'NguoiTao' => 'admin'],
            ]);
        });

        $this->withSession(['MaTK' => 3])
            ->get('/baocao/export-json')
            ->assertOk()
            ->assertSee('Tong hop');
    }

    public function test_audit_log_export_json_returns_filtered_logs(): void
    {
        $this->mock(PermissionService::class, fn ($mock) => $mock->shouldReceive('hasPermission', 'hasPermissionFromCache')->andReturnTrue());
        $this->mock(AppAuditLogService::class, function ($mock) {
            $mock->shouldReceive('readFilteredRows')->once()->with('ERROR', 'csrf')->andReturn([
                ['time' => '2026-04-09 10:00:00', 'level' => 'ERROR', 'message' => 'CSRF mismatch', 'context' => '{}'],
            ]);
        });

        $this->withSession(['MaTK' => 3])
            ->get('/audit-log/export-json?level=ERROR&q=csrf')
            ->assertOk()
            ->assertSee('CSRF mismatch');
    }
}
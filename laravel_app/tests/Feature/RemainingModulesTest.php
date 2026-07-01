<?php

namespace Tests\Feature;

use App\Services\ChatbotMonitorService;
use App\Services\PermissionService;
use App\Services\RecruitmentService;
use App\Services\ReportService;
use App\Services\TrainingService;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class RemainingModulesTest extends TestCase
{
    public function test_recruitment_index_renders_for_authorized_session(): void
    {
        $this->mock(PermissionService::class, fn($mock) => $mock->shouldReceive('hasPermission', 'hasPermissionFromCache')->andReturnTrue());
        $this->mock(RecruitmentService::class, fn($mock) => $mock->shouldReceive('paginate')->andReturn(new LengthAwarePaginator([
            (object) ['MaDTD' => 1, 'TenDotTuyenDung' => 'Tuyen dung IT', 'ViTriTuyenDung' => 'Nhan vien', 'SoLuong' => 2, 'SoHoSo' => 5, 'TrangThai' => 'Đang tuyển'],
        ], 1, 12)));

        $this->withSession(['MaTK' => 3])->get('/tuyendung')->assertOk()->assertSee('Tuyen dung IT');
    }

    public function test_training_index_renders_for_authorized_session(): void
    {
        $this->mock(PermissionService::class, fn($mock) => $mock->shouldReceive('hasPermission', 'hasPermissionFromCache')->andReturnTrue());
        $this->mock(TrainingService::class, fn($mock) => $mock->shouldReceive('paginate')->andReturn(new LengthAwarePaginator([
            (object) ['MaKDT' => 1, 'TenKhoaDaoTao' => 'Laravel', 'DonViToChuc' => 'Noi bo', 'SoHocVien' => 10, 'TrangThai' => 'Đang đào tạo'],
        ], 1, 12)));

        $this->withSession(['MaTK' => 3])->get('/daotao')->assertOk()->assertSee('Laravel');
    }

    public function test_reports_index_renders_for_authorized_session(): void
    {
        $this->mock(PermissionService::class, fn($mock) => $mock->shouldReceive('hasPermission', 'hasPermissionFromCache')->andReturnTrue());
        $this->mock(ReportService::class, fn($mock) => $mock->shouldReceive('paginate')->andReturn(new LengthAwarePaginator([
            (object) ['MaBC' => 1, 'TenBaoCao' => 'Bao cao tong hop', 'LoaiBaoCao' => 'Nhân sự', 'NguoiTao' => 'admin', 'ThoiDiemTao' => '2026-04-01 10:00:00'],
        ], 1, 12)));

        $this->withSession(['MaTK' => 3])->get('/baocao')->assertOk()->assertSee('Bao cao tong hop');
    }

    public function test_chatbot_index_renders_for_authorized_session(): void
    {
        $this->mock(PermissionService::class, fn($mock) => $mock->shouldReceive('hasPermission', 'hasPermissionFromCache')->andReturnTrue());
        $this->mock(ChatbotMonitorService::class, fn($mock) => $mock->shouldReceive('paginateSessions')->andReturn(new LengthAwarePaginator([
            (object) ['id' => 1, 'session_key' => 'abc', 'username' => 'admin', 'role_name' => 'Admin', 'MessageCount' => 2, 'DraftCount' => 1, 'last_interaction_at' => '2026-04-01 10:00:00'],
        ], 1, 15)));

        $this->withSession(['MaTK' => 3])->get('/chatbot')->assertOk()->assertSee('admin');
    }
}
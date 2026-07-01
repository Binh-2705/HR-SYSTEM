<?php

namespace Tests\Feature;

use App\Services\EmployeeProfileAdminService;
use App\Services\PermissionService;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class EmployeeProfileAdminPagesTest extends TestCase
{
    public function test_employee_profile_create_page_renders(): void
    {
        config(['laravel_resource_modules.employee-profiles.field_lookups' => []]);

        $this->mock(PermissionService::class, fn ($mock) => $mock->shouldReceive('hasPermission', 'hasPermissionFromCache')->andReturnTrue());

        $this->withSession(['MaTK' => 3, 'quyen' => ['sua_nhanvien'], 'taikhoan' => ['VaiTro' => 'Admin']])
            ->get('/hosocanhan/create')
            ->assertOk()
            ->assertSee('Hồ sơ nhân viên', false);
    }

    public function test_employee_profile_detail_page_renders(): void
    {
        $this->mock(PermissionService::class, fn ($mock) => $mock->shouldReceive('hasPermission', 'hasPermissionFromCache')->andReturnTrue());
        $this->mock(EmployeeProfileAdminService::class, function ($mock) {
            $mock->shouldReceive('profileDetail')->once()->with(15)->andReturn([
                'MaHoSo' => 15,
                'MaNV' => 8,
                'HoTen' => 'Pham Thi D',
                'TenPB' => 'Nhan su',
                'TenCV' => 'Chuyen vien',
                'TrangThaiHonNhan' => 'Độc thân',
            ]);
        });

        $this->withSession(['MaTK' => 3, 'quyen' => ['xem_nhanvien']])
            ->get('/hosocanhan/15/detail')
            ->assertOk()
            ->assertSee('Pham Thi D');
    }

    public function test_employee_profile_review_requests_page_renders_for_manager(): void
    {
        $this->mock(PermissionService::class, fn ($mock) => $mock->shouldReceive('hasPermission', 'hasPermissionFromCache')->andReturnTrue());
        $this->mock(EmployeeProfileAdminService::class, function ($mock) {
            $mock->shouldReceive('pendingRequests')->once()->andReturn([
                ['id' => 4, 'MaNV' => 8, 'HoTen' => 'Pham Thi D', 'DienThoai' => '0909', 'note' => 'Xin cap nhat', 'payload' => ['CCCD' => '123456789012']],
            ]);
        });

        $this->withSession(['MaTK' => 3, 'quyen' => ['xem_nhanvien'], 'taikhoan' => ['VaiTro' => 'QuanLy']])
            ->get('/hosocanhan/review-requests')
            ->assertOk()
            ->assertSee('Pham Thi D');
    }

    public function test_employee_profile_review_request_resolution_redirects(): void
    {
        $this->mock(PermissionService::class, fn ($mock) => $mock->shouldReceive('hasPermission', 'hasPermissionFromCache')->andReturnTrue());
        $this->mock(EmployeeProfileAdminService::class, function ($mock) {
            $mock->shouldReceive('resolveRequest')->once()->with(4, 'approve', 3, 'OK');
        });

        $this->withSession(['MaTK' => 3, 'quyen' => ['xem_nhanvien'], 'taikhoan' => ['VaiTro' => 'Admin']])
            ->post('/hosocanhan/review-requests/4', ['decision' => 'approve', 'review_note' => 'OK'])
            ->assertRedirect('/hosocanhan/review-requests');
    }
}
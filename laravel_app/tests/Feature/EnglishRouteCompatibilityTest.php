<?php

namespace Tests\Feature;

use App\Services\EmployeeProfileAdminService;
use App\Services\HrEmployeeService;
use App\Services\PermissionService;
use App\Services\RecruitmentService;
use App\Services\RolePermissionService;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class EnglishRouteCompatibilityTest extends TestCase
{
    public function test_employees_route_still_renders_for_authorized_session(): void
    {
        $this->mock(PermissionService::class, fn ($mock) => $mock->shouldReceive('hasPermission', 'hasPermissionFromCache')->andReturnTrue());
        $this->mock(HrEmployeeService::class, function ($mock) {
            $mock->shouldReceive('paginate')->andReturn(new LengthAwarePaginator([
                (object) [
                    'MaNV' => 1,
                    'HoTen' => 'Nguyen Van A',
                    'Email' => 'a@example.com',
                    'TenPB' => 'Nhan Su',
                    'TenCV' => 'Nhan vien',
                    'TenBac' => 'Bac 1',
                    'TrangThai' => 'Đang làm',
                ],
            ], 1, 12));
            $mock->shouldReceive('options')->andReturn([
                'departments' => collect([(object) ['MaPB' => 1, 'TenPB' => 'Nhan Su']]),
                'positions' => collect(),
                'salaryGrades' => collect(),
            ]);
        });

        $this->withSession(['MaTK' => 3])->get('/employees')->assertOk()->assertSee('Nguyen Van A');
    }

    public function test_recruitment_route_still_renders_for_authorized_session(): void
    {
        $this->mock(PermissionService::class, fn ($mock) => $mock->shouldReceive('hasPermission', 'hasPermissionFromCache')->andReturnTrue());
        $this->mock(RecruitmentService::class, fn ($mock) => $mock->shouldReceive('paginate')->andReturn(new LengthAwarePaginator([
            (object) ['MaDTD' => 1, 'TenDotTuyenDung' => 'Tuyen dung IT', 'ViTriTuyenDung' => 'Nhan vien', 'SoLuong' => 2, 'SoHoSo' => 5, 'TrangThai' => 'Đang tuyển'],
        ], 1, 12)));

        $this->withSession(['MaTK' => 3])->get('/recruitment')->assertOk()->assertSee('Tuyen dung IT');
    }

    public function test_permission_matrix_route_still_renders_for_authorized_session(): void
    {
        $this->mock(PermissionService::class, fn ($mock) => $mock->shouldReceive('hasPermission', 'hasPermissionFromCache')->andReturnTrue());
        $this->mock(RolePermissionService::class, function ($mock) {
            $mock->shouldReceive('indexData')->once()->andReturn([
                'roles' => [
                    ['MaVaiTro' => 1, 'TenVaiTro' => 'Admin'],
                ],
                'functions' => [
                    ['MaCN' => 10, 'TenChucNang' => 'xem_nhanvien'],
                ],
                'permissionsByRole' => [1 => [10]],
                'groupOrder' => ['Nhan vien'],
            ]);
            $mock->shouldReceive('groupFunctions')->once()->andReturn([
                'Nhan vien' => [
                    ['MaCN' => 10, 'TenChucNang' => 'xem_nhanvien'],
                ],
            ]);
        });

        $this->withSession(['MaTK' => 9, 'quyen' => ['xem_phanquyen', 'sua_taikhoan']])
            ->get('/permission-matrix')
            ->assertOk()
            ->assertSee('Admin');
    }

    public function test_employee_profiles_review_requests_route_still_renders_for_manager(): void
    {
        $this->mock(PermissionService::class, fn ($mock) => $mock->shouldReceive('hasPermission', 'hasPermissionFromCache')->andReturnTrue());
        $this->mock(EmployeeProfileAdminService::class, function ($mock) {
            $mock->shouldReceive('pendingRequests')->once()->andReturn([
                ['id' => 4, 'MaNV' => 8, 'HoTen' => 'Pham Thi D', 'DienThoai' => '0909', 'note' => 'Xin cap nhat', 'payload' => ['CCCD' => '123456789012']],
            ]);
        });

        $this->withSession(['MaTK' => 3, 'quyen' => ['xem_nhanvien'], 'taikhoan' => ['VaiTro' => 'QuanLy']])
            ->get('/employee-profiles/review-requests')
            ->assertOk()
            ->assertSee('Pham Thi D');
    }
}
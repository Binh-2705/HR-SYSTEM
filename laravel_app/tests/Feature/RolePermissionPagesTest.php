<?php

namespace Tests\Feature;

use App\Services\PermissionService;
use App\Services\RolePermissionService;
use Tests\TestCase;

class RolePermissionPagesTest extends TestCase
{
    public function test_role_permissions_page_renders_in_laravel(): void
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
                'groupOrder' => ['Nhan vien', 'Khac'],
            ]);
            $mock->shouldReceive('groupFunctions')->once()->andReturn([
                'Nhan vien' => [
                    ['MaCN' => 10, 'TenChucNang' => 'xem_nhanvien'],
                ],
            ]);
        });

        $this->withSession(['MaTK' => 9, 'quyen' => ['xem_phanquyen', 'sua_taikhoan']])
            ->get('/phanquyen')
            ->assertOk()
            ->assertSee('Admin')
            ->assertSee('xem_nhanvien');
    }

    public function test_role_permission_update_redirects_with_success(): void
    {
        $this->mock(PermissionService::class, fn ($mock) => $mock->shouldReceive('hasPermission', 'hasPermissionFromCache')->andReturnTrue());
        $this->mock(RolePermissionService::class, function ($mock) {
            $mock->shouldReceive('updateRolePermissions')->once()->with(2, [4, 5]);
        });

        $this->withSession(['MaTK' => 9, 'quyen' => ['xem_phanquyen', 'sua_taikhoan']])
            ->post('/phanquyen/2', ['chucnang' => [4, 5]])
            ->assertRedirect('/phanquyen');
    }

    public function test_restore_default_permissions_redirects_with_success(): void
    {
        $this->mock(PermissionService::class, fn ($mock) => $mock->shouldReceive('hasPermission', 'hasPermissionFromCache')->andReturnTrue());
        $this->mock(RolePermissionService::class, function ($mock) {
            $mock->shouldReceive('restoreDefaultPermissions')->once()->with(3)->andReturnTrue();
        });

        $this->withSession(['MaTK' => 9, 'quyen' => ['xem_phanquyen', 'sua_taikhoan']])
            ->post('/phanquyen/3/khoi-phuc-mac-dinh')
            ->assertRedirect('/phanquyen');
    }
}
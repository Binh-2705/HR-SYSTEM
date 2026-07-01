<?php

namespace Tests\Feature;

use App\Services\ContractAdminService;
use App\Services\PermissionService;
use Tests\TestCase;

class ContractAdminActionsTest extends TestCase
{
    public function test_contract_salary_history_page_renders(): void
    {
        $this->mock(PermissionService::class, fn ($mock) => $mock->shouldReceive('hasPermission', 'hasPermissionFromCache')->andReturnTrue());
        $this->mock(ContractAdminService::class, function ($mock) {
            $mock->shouldReceive('contractDetail')->once()->with(12)->andReturn([
                'MaHopDong' => 12,
                'SoHopDong' => 'HD-12',
                'HoTen' => 'Tran Van C',
                'TenBac' => 'Bac 2',
                'LuongThucTe' => 12000000,
            ]);
            $mock->shouldReceive('salaryHistory')->once()->with(12)->andReturn([
                ['NgayApDung' => '2026-04-01', 'LuongCu' => 10000000, 'LuongMoi' => 12000000, 'LyDo' => 'Dieu chinh'],
            ]);
        });

        $this->withSession(['MaTK' => 3, 'quyen' => ['xem_lich_su_luong']])
            ->get('/hopdong/12/salary-history')
            ->assertOk()
            ->assertSee('HD-12')
            ->assertSee('Tran Van C');
    }

    public function test_contract_renew_redirects_to_contracts_index(): void
    {
        $this->mock(PermissionService::class, fn ($mock) => $mock->shouldReceive('hasPermission', 'hasPermissionFromCache')->andReturnTrue());
        $this->mock(ContractAdminService::class, function ($mock) {
            $mock->shouldReceive('renewContract')->once()->with(12, [
                'SoHopDong' => 'HD-NEW-12',
                'LoaiHopDong' => 'Xác định thời hạn',
                'NgayBatDau' => '2026-05-01',
                'NgayKetThuc' => '2027-05-01',
                'GhiChu' => 'Gia han',
            ]);
        });

        $this->withSession(['MaTK' => 3, 'quyen' => ['giahan_hopdong']])
            ->post('/hopdong/12/renew', [
                'SoHopDong' => 'HD-NEW-12',
                'LoaiHopDong' => 'Xác định thời hạn',
                'NgayBatDau' => '2026-05-01',
                'NgayKetThuc' => '2027-05-01',
                'GhiChu' => 'Gia han',
            ])
            ->assertRedirect('/hopdong');
    }

    public function test_contract_terminate_redirects_to_contracts_index(): void
    {
        $this->mock(PermissionService::class, fn ($mock) => $mock->shouldReceive('hasPermission', 'hasPermissionFromCache')->andReturnTrue());
        $this->mock(ContractAdminService::class, function ($mock) {
            $mock->shouldReceive('terminateContract')->once()->with(12);
        });

        $this->withSession(['MaTK' => 3, 'quyen' => ['chamdut_hopdong']])
            ->post('/hopdong/12/terminate')
            ->assertRedirect('/hopdong');
    }
}
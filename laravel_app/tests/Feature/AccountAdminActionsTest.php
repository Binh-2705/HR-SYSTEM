<?php

namespace Tests\Feature;

use App\Services\AccountSecurityService;
use App\Services\GenericResourceModuleService;
use App\Services\PermissionService;
use Tests\TestCase;

class AccountAdminActionsTest extends TestCase
{
    public function test_reset_temporary_password_redirects_to_accounts_index(): void
    {
        $this->mock(PermissionService::class, fn ($mock) => $mock->shouldReceive('hasPermission', 'hasPermissionFromCache')->andReturnTrue());
        $this->mock(AccountSecurityService::class, function ($mock) {
            $mock->shouldReceive('getById')->once()->with(5)->andReturn([
                'MaTK' => 5,
                'TenDangNhap' => 'demo.user',
            ]);
            $mock->shouldReceive('updatePassword')->once()->withArgs(function (int $id, string $hash, bool $forceChange) {
                return $id === 5 && $hash !== '' && $forceChange === true;
            })->andReturnTrue();
        });

        $this->withSession(['MaTK' => 9, 'quyen' => ['sua_taikhoan']])
            ->post('/taikhoan/5/reset-temporary')
            ->assertRedirect('/taikhoan');
    }

    public function test_account_legacy_delete_bridge_redirects_to_accounts_index(): void
    {
        $this->mock(PermissionService::class, fn ($mock) => $mock->shouldReceive('hasPermission', 'hasPermissionFromCache')->andReturnTrue());
        $this->mock(GenericResourceModuleService::class, function ($mock) {
            $mock->shouldReceive('delete')->once()->with('accounts', '7');
        });

        $this->withSession(['MaTK' => 9, 'quyen' => ['xoa_taikhoan']])
            ->get('/taikhoan/7/delete-legacy')
            ->assertRedirect('/taikhoan');
    }
}
<?php

namespace Tests\Feature;

use App\Services\AccountSecurityService;
use Tests\TestCase;

class AccountSecurityPagesTest extends TestCase
{
    public function test_settings_page_renders_for_authenticated_session(): void
    {
        $this->mock(AccountSecurityService::class, function ($mock) {
            $mock->shouldReceive('isSessionRevoked')->andReturnFalse();
            $mock->shouldReceive('touchSessionAudit')->andReturnNull();
            $mock->shouldReceive('isPasswordChangeRequired')->andReturnFalse();
            $mock->shouldReceive('getById')->andReturn([
                'MaTK' => 3,
                'TenDangNhap' => 'admin',
                'VaiTro' => 'Admin',
                'MaNV' => 'L001',
                'BuocDoiMatKhau' => 0,
            ]);
            $mock->shouldReceive('registerSessionAudit')->andReturnNull();
            $mock->shouldReceive('getRecentSessions')->andReturn([]);
        });

        $this->withSession([
            'MaTK' => 3,
            'taikhoan' => ['TenDangNhap' => 'admin'],
            'quyen' => [],
            'session_marker' => 'marker123',
        ])->get('/settings')->assertOk()->assertSee('settings', false);
    }

    public function test_forgot_password_page_renders(): void
    {
        $this->get('/forgot-password')->assertOk()->assertSee('forgot', false);
    }
}
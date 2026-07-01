<?php

namespace Tests\Unit;

use App\Services\InternalApiClient;
use App\Services\PermissionService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PermissionServiceTest extends TestCase
{
    private PermissionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $client = $this->createMock(InternalApiClient::class);
        $this->service = new PermissionService($client);
    }

    public function test_has_permission_returns_true_when_permission_exists(): void
    {
        Cache::shouldReceive('remember')
            ->once()
            ->with('permissions_tk_5', 300, \Closure::class)
            ->andReturn(['xem_nhanvien', 'xem_luong', 'xem_phongban']);

        $result = $this->service->hasPermission(5, 'xem_luong');

        $this->assertTrue($result);
    }

    public function test_has_permission_returns_false_when_permission_missing(): void
    {
        Cache::shouldReceive('remember')
            ->once()
            ->andReturn(['xem_nhanvien']);

        $result = $this->service->hasPermission(5, 'xoa_nhanvien');

        $this->assertFalse($result);
    }

    public function test_has_permission_returns_false_for_empty_permissions(): void
    {
        Cache::shouldReceive('remember')->once()->andReturn([]);

        $this->assertFalse($this->service->hasPermission(99, 'bat_ky_quyen_gi'));
    }

    public function test_clear_permissions_cache_removes_cache_key(): void
    {
        Cache::shouldReceive('forget')
            ->once()
            ->with('permissions_tk_7');

        $this->service->clearPermissionsCache(7);
    }

    public function test_has_permission_from_cache_is_alias_for_has_permission(): void
    {
        Cache::shouldReceive('remember')->once()->andReturn(['xem_chamcong']);

        $this->assertTrue($this->service->hasPermissionFromCache(1, 'xem_chamcong'));
    }

    public function test_get_permissions_caches_per_account(): void
    {
        // Hai tài khoản khác nhau phải tạo 2 cache key khác nhau
        Cache::shouldReceive('remember')
            ->once()
            ->with('permissions_tk_1', 300, \Closure::class)
            ->andReturn(['xem_nhanvien']);

        Cache::shouldReceive('remember')
            ->once()
            ->with('permissions_tk_2', 300, \Closure::class)
            ->andReturn(['xem_luong']);

        $this->assertContains('xem_nhanvien', $this->service->getPermissionsByAccountId(1));
        $this->assertContains('xem_luong', $this->service->getPermissionsByAccountId(2));
    }
}

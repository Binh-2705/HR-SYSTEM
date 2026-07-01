<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\AccountBizController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AccountBizControllerCacheKeyTest extends TestCase
{
    public function test_show_uses_lookup_versioned_cache_key_and_expected_ttl(): void
    {
        Cache::shouldReceive('get')
            ->once()
            ->with('api:account:lookup:version', 1)
            ->andReturn(6);

        Cache::shouldReceive('remember')
            ->once()
            ->withArgs(function (string $key, int $ttl, callable $resolver): bool {
                $this->assertSame('api:account:show:v6:id:42', $key);
                $this->assertSame(60, $ttl);
                $this->assertIsCallable($resolver);

                return true;
            })
            ->andReturn([
                'found' => true,
                'data' => ['MaTK' => 42, 'TenDangNhap' => 'alice'],
            ]);

        $controller = new AccountBizController();
        $response = $controller->show(42);
        $payload = $response->getData(true);

        $this->assertTrue($payload['ok']);
        $this->assertSame(42, $payload['data']['MaTK']);
    }

    public function test_list_sessions_uses_per_account_versioned_cache_key_and_expected_ttl(): void
    {
        Cache::shouldReceive('get')
            ->once()
            ->with('api:account:sessions:version:7', 1)
            ->andReturn(3);

        Cache::shouldReceive('remember')
            ->once()
            ->withArgs(function (string $key, int $ttl, callable $resolver): bool {
                $this->assertSame('api:account:list-sessions:v3:ma_tk:7', $key);
                $this->assertSame(15, $ttl);
                $this->assertIsCallable($resolver);

                return true;
            })
            ->andReturn([
                'data' => [
                    ['session_marker' => 'abc', 'MaTK' => 7],
                ],
            ]);

        $controller = new AccountBizController();
        $response = $controller->listSessions(7);
        $payload = $response->getData(true);

        $this->assertTrue($payload['ok']);
        $this->assertCount(1, $payload['data']);
        $this->assertSame('abc', $payload['data'][0]['session_marker']);
    }

    public function test_show_uses_fallback_version_when_primary_cache_get_fails(): void
    {
        $fallbackStore = \Mockery::mock(\Illuminate\Contracts\Cache\Repository::class);

        Cache::shouldReceive('get')
            ->once()
            ->with('api:account:lookup:version', 1)
            ->andThrow(new \RuntimeException('primary get failed'));

        Cache::shouldReceive('store')
            ->once()
            ->with('file')
            ->andReturn($fallbackStore);

        $fallbackStore->shouldReceive('get')
            ->once()
            ->with('api:account:lookup:version', 1)
            ->andReturn(10);

        Cache::shouldReceive('remember')
            ->once()
            ->withArgs(function (string $key, int $ttl, callable $resolver): bool {
                $this->assertSame('api:account:show:v10:id:5', $key);
                $this->assertSame(60, $ttl);
                $this->assertIsCallable($resolver);

                return true;
            })
            ->andReturn([
                'found' => true,
                'data' => ['MaTK' => 5],
            ]);

        $controller = new AccountBizController();
        $response = $controller->show(5);
        $payload = $response->getData(true);

        $this->assertTrue($payload['ok']);
        $this->assertSame(5, $payload['data']['MaTK']);
    }
}

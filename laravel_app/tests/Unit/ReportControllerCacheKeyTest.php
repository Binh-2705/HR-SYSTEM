<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\ReportController;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use ReflectionClass;
use RuntimeException;
use Tests\TestCase;

class ReportControllerCacheKeyTest extends TestCase
{
    public function test_index_cache_key_contains_version_and_uses_expected_ttl(): void
    {
        Cache::shouldReceive('get')
            ->once()
            ->with('api:reports:list:version', 1)
            ->andReturn(7);

        Cache::shouldReceive('remember')
            ->once()
            ->withArgs(function (string $key, int $ttl, callable $resolver): bool {
                $this->assertStringStartsWith('api:reports:index:v7:', $key);
                $this->assertSame(120, $ttl);
                $this->assertIsCallable($resolver);

                return true;
            })
            ->andReturn([
                'ok' => true,
                'data' => [],
                'pagination' => [
                    'total' => 0,
                    'per_page' => 15,
                    'current_page' => 1,
                    'last_page' => 0,
                ],
            ]);

        $controller = new ReportController();
        $request = Request::create('/api/reports', 'GET', [
            'filters' => ['q' => 'abc'],
            'per_page' => 15,
            'page' => 1,
        ]);

        $response = $controller->index($request);
        $payload = $response->getData(true);

        $this->assertTrue($payload['ok']);
    }

    public function test_export_cache_key_contains_version_and_uses_expected_ttl(): void
    {
        Cache::shouldReceive('get')
            ->once()
            ->with('api:reports:list:version', 1)
            ->andReturn(11);

        Cache::shouldReceive('remember')
            ->once()
            ->withArgs(function (string $key, int $ttl, callable $resolver): bool {
                $this->assertStringStartsWith('api:reports:export:v11:', $key);
                $this->assertSame(120, $ttl);
                $this->assertIsCallable($resolver);

                return true;
            })
            ->andReturn([
                'ok' => true,
                'data' => [],
            ]);

        $controller = new ReportController();
        $request = Request::create('/api/reports/export', 'GET', [
            'filters' => ['type' => 'monthly'],
        ]);

        $response = $controller->export($request);
        $payload = $response->getData(true);

        $this->assertTrue($payload['ok']);
    }

    public function test_index_uses_fallback_version_when_primary_cache_get_fails(): void
    {
        $fallbackStore = \Mockery::mock(CacheRepository::class);

        Cache::shouldReceive('get')
            ->once()
            ->with('api:reports:list:version', 1)
            ->andThrow(new RuntimeException('primary get failed'));

        Cache::shouldReceive('store')
            ->once()
            ->with('file')
            ->andReturn($fallbackStore);

        $fallbackStore->shouldReceive('get')
            ->once()
            ->with('api:reports:list:version', 1)
            ->andReturn(9);

        Cache::shouldReceive('remember')
            ->once()
            ->withArgs(function (string $key, int $ttl, callable $resolver): bool {
                $this->assertStringStartsWith('api:reports:index:v9:', $key);
                $this->assertSame(120, $ttl);
                $this->assertIsCallable($resolver);

                return true;
            })
            ->andReturn([
                'ok' => true,
                'data' => [],
                'pagination' => [
                    'total' => 0,
                    'per_page' => 15,
                    'current_page' => 1,
                    'last_page' => 0,
                ],
            ]);

        $controller = new ReportController();
        $request = Request::create('/api/reports', 'GET', [
            'filters' => ['q' => 'fallback-version'],
            'per_page' => 15,
            'page' => 1,
        ]);

        $response = $controller->index($request);
        $payload = $response->getData(true);

        $this->assertTrue($payload['ok']);
    }

    public function test_export_uses_fallback_store_when_primary_remember_fails(): void
    {
        $fallbackStore = \Mockery::mock(CacheRepository::class);

        Cache::shouldReceive('get')
            ->once()
            ->with('api:reports:list:version', 1)
            ->andReturn(5);

        Cache::shouldReceive('remember')
            ->once()
            ->withArgs(function (string $key, int $ttl, callable $resolver): bool {
                $this->assertStringStartsWith('api:reports:export:v5:', $key);
                $this->assertSame(120, $ttl);
                $this->assertIsCallable($resolver);

                return true;
            })
            ->andThrow(new RuntimeException('primary remember failed'));

        Cache::shouldReceive('store')
            ->once()
            ->with('file')
            ->andReturn($fallbackStore);

        $fallbackStore->shouldReceive('remember')
            ->once()
            ->withArgs(function (string $key, int $ttl, callable $resolver): bool {
                $this->assertStringStartsWith('api:reports:export:v5:', $key);
                $this->assertSame(120, $ttl);
                $this->assertIsCallable($resolver);

                return true;
            })
            ->andReturn([
                'ok' => true,
                'data' => [['id' => 1]],
            ]);

        $controller = new ReportController();
        $request = Request::create('/api/reports/export', 'GET', [
            'filters' => ['type' => 'monthly'],
        ]);

        $response = $controller->export($request);
        $payload = $response->getData(true);

        $this->assertTrue($payload['ok']);
        $this->assertCount(1, $payload['data']);
    }

    public function test_remember_cache_returns_resolver_when_primary_and_fallback_fail(): void
    {
        $fallbackStore = \Mockery::mock(CacheRepository::class);

        Cache::shouldReceive('remember')
            ->once()
            ->with('api:test:key', 120, \Mockery::type('callable'))
            ->andThrow(new RuntimeException('primary remember failed'));

        Cache::shouldReceive('store')
            ->once()
            ->with('file')
            ->andReturn($fallbackStore);

        $fallbackStore->shouldReceive('remember')
            ->once()
            ->with('api:test:key', 120, \Mockery::type('callable'))
            ->andThrow(new RuntimeException('fallback remember failed'));

        $controller = new ReportController();
        $reflection = new ReflectionClass($controller);
        $method = $reflection->getMethod('rememberCache');
        $method->setAccessible(true);

        $resolved = $method->invoke($controller, 'api:test:key', fn (): array => [
            'ok' => true,
            'data' => ['from' => 'resolver'],
        ], 120);

        $this->assertSame(['ok' => true, 'data' => ['from' => 'resolver']], $resolved);
    }
}

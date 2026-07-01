<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\AccountBizController;
use App\Http\Controllers\Api\PayrollController;
use App\Http\Controllers\Api\RecruitmentController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\TrainingController;
use Illuminate\Support\Facades\Cache;
use ReflectionClass;
use Tests\TestCase;

class ApiListCacheVersioningTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
    }

    public function test_payroll_list_cache_version_bumps(): void
    {
        Cache::forever('api:payroll:list:version', 1);

        $controller = new PayrollController();
        $this->invokePrivate($controller, 'bumpListCacheVersion');

        $this->assertSame(2, (int) Cache::get('api:payroll:list:version'));
    }

    public function test_recruitment_list_cache_version_bumps(): void
    {
        Cache::forever('api:recruitment:list:version', 1);

        $controller = new RecruitmentController();
        $this->invokePrivate($controller, 'bumpListCacheVersion');

        $this->assertSame(2, (int) Cache::get('api:recruitment:list:version'));
    }

    public function test_training_list_cache_version_bumps(): void
    {
        Cache::forever('api:training:list:version', 1);

        $controller = new TrainingController();
        $this->invokePrivate($controller, 'bumpListCacheVersion');

        $this->assertSame(2, (int) Cache::get('api:training:list:version'));
    }

    public function test_report_list_cache_version_bumps(): void
    {
        Cache::forever('api:reports:list:version', 1);

        $controller = new ReportController();
        $this->invokePrivate($controller, 'bumpListCacheVersion');

        $this->assertSame(2, (int) Cache::get('api:reports:list:version'));
    }

    public function test_account_lookup_cache_version_bumps(): void
    {
        Cache::forever('api:account:lookup:version', 1);

        $controller = new AccountBizController();
        $this->invokePrivate($controller, 'bumpAccountLookupVersion');

        $this->assertSame(2, (int) Cache::get('api:account:lookup:version'));
    }

    public function test_account_session_cache_version_bumps(): void
    {
        Cache::forever('api:account:sessions:version:123', 1);

        $controller = new AccountBizController();
        $this->invokePrivate($controller, 'bumpAccountSessionVersion', [123]);

        $this->assertSame(2, (int) Cache::get('api:account:sessions:version:123'));
    }

    private function invokePrivate(object $instance, string $method, array $arguments = []): mixed
    {
        $reflection = new ReflectionClass($instance);
        $target = $reflection->getMethod($method);
        $target->setAccessible(true);

        return $target->invokeArgs($instance, $arguments);
    }
}

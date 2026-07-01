<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiWriteAuthorizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['services.service_gateway.token' => 'test-token']);
    }

    public function test_write_api_rejects_user_context_without_permissions(): void
    {
        $response = $this
            ->withHeader('X-Service-Token', 'test-token')
            ->withHeader('X-Account-Id', '7')
            ->withHeader('X-Account-Role', 'NhanVien')
            ->postJson('/api/reports', ['TenBaoCao' => 'Bao cao test']);

        $response->assertStatus(403)
            ->assertJsonPath('ok', false);
    }

    public function test_all_token_protected_write_routes_require_write_permission_middleware(): void
    {
        $writeMethods = ['POST', 'PUT', 'PATCH', 'DELETE'];
        $missing = [];

        foreach (app('router')->getRoutes() as $route) {
            $uri = $route->uri();
            if (!str_starts_with($uri, 'api/')) {
                continue;
            }

            $methods = array_values(array_intersect($route->methods(), $writeMethods));
            if (empty($methods)) {
                continue;
            }

            if (!$this->routeHasMiddleware($route, 'api.token')) {
                continue;
            }

            if ($this->routeHasMiddleware($route, 'api.write.permission')) {
                continue;
            }

            $missing[] = implode('|', $methods) . ' ' . $uri;
        }

        $this->assertSame(
            [],
            $missing,
            "Token-protected write routes missing api.write.permission:\n" . implode("\n", $missing)
        );
    }

    private function routeHasMiddleware($route, string $alias): bool
    {
        foreach ($route->gatherMiddleware() as $middleware) {
            if ($middleware === $alias || str_starts_with($middleware, $alias . ':')) {
                return true;
            }
        }

        return false;
    }

    public function test_write_api_with_permission_context_passes_guard_and_reaches_validation(): void
    {
        $response = $this
            ->withHeader('X-Service-Token', 'test-token')
            ->withHeader('X-Account-Id', '7')
            ->withHeader('X-Account-Role', 'NhanVien')
            ->withHeader('X-Account-Permissions', 'them_baocao')
            ->postJson('/api/reports', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['TenBaoCao']);
    }

    public function test_write_api_with_wrong_action_permission_is_forbidden(): void
    {
        $response = $this
            ->withHeader('X-Service-Token', 'test-token')
            ->withHeader('X-Account-Id', '7')
            ->withHeader('X-Account-Role', 'NhanVien')
            ->withHeader('X-Account-Permissions', 'xem_baocao')
            ->postJson('/api/reports', ['TenBaoCao' => 'Bao cao test']);

        $response->assertStatus(403)
            ->assertJsonPath('ok', false);
    }

    public function test_write_api_without_user_context_keeps_backward_compatibility(): void
    {
        $response = $this
            ->withHeader('X-Service-Token', 'test-token')
            ->postJson('/api/reports', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['TenBaoCao']);
    }

    public function test_modules_write_infers_permission_from_module_config(): void
    {
        $response = $this
            ->withHeader('X-Service-Token', 'test-token')
            ->withHeader('X-Account-Id', '7')
            ->withHeader('X-Account-Role', 'NhanVien')
            ->withHeader('X-Account-Permissions', 'xem_taikhoan')
            ->postJson('/api/modules/accounts', []);

        $response->assertStatus(403)
            ->assertJsonPath('ok', false);
    }

    public function test_payroll_run_monthly_requires_tinh_luong_thang_permission(): void
    {
        $response = $this
            ->withHeader('X-Service-Token', 'test-token')
            ->withHeader('X-Account-Id', '7')
            ->withHeader('X-Account-Role', 'NhanVien')
            ->withHeader('X-Account-Permissions', 'xem_luong')
            ->postJson('/api/payroll/run-monthly', ['month' => 4, 'year' => 2026]);

        $response->assertStatus(403)
            ->assertJsonPath('ok', false);
    }

    public function test_contract_renew_requires_sua_hopdong_permission(): void
    {
        $response = $this
            ->withHeader('X-Service-Token', 'test-token')
            ->withHeader('X-Account-Id', '7')
            ->withHeader('X-Account-Role', 'NhanVien')
            ->withHeader('X-Account-Permissions', 'xem_hopdong')
            ->postJson('/api/biz/contracts/10/renew', []);

        $response->assertStatus(403)
            ->assertJsonPath('ok', false);
    }

    public function test_leave_request_approve_requires_sua_nghiphep_permission(): void
    {
        $response = $this
            ->withHeader('X-Service-Token', 'test-token')
            ->withHeader('X-Account-Id', '7')
            ->withHeader('X-Account-Role', 'NhanVien')
            ->withHeader('X-Account-Permissions', 'xem_nghiphep')
            ->postJson('/api/biz/leave-requests/10/approve', []);

        $response->assertStatus(403)
            ->assertJsonPath('ok', false);
    }
}

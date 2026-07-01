<?php

namespace Tests\Feature;

use App\Services\PermissionService;
use Tests\TestCase;

class ServiceConsoleTest extends TestCase
{
    public function test_service_console_requires_login(): void
    {
        $response = $this->get('/services');

        $response->assertRedirect(route('login.form'));
    }

    public function test_service_console_index_requires_permission(): void
    {
        $this->mock(PermissionService::class, function ($mock) {
            $mock->shouldReceive('hasPermission', 'hasPermissionFromCache')->andReturnFalse();
        });

        $response = $this->withSession(['MaTK' => 3])->get('/services');

        $response->assertForbidden();
    }

    public function test_service_console_index_renders_for_authorized_session(): void
    {
        $this->mock(PermissionService::class, function ($mock) {
            $mock->shouldReceive('hasPermission', 'hasPermissionFromCache')->andReturnTrue();
        });

        $response = $this->withSession(['MaTK' => 3])->get('/services');

        $response->assertOk()->assertSee('Bang dich vu');
    }
}
<?php

namespace Tests\Feature;

use Tests\TestCase;

class ServiceAliasRoutesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['services.service_gateway.token' => 'test-token']);
    }

    public function test_service_alias_catalog_endpoint_is_removed_after_gateway_deprecation(): void
    {
        $response = $this->withHeader('X-Service-Token', 'test-token')->getJson('/api/hr');

        $response->assertStatus(404);
    }

    public function test_service_alias_resource_route_is_removed_after_gateway_deprecation(): void
    {
        $response = $this->withHeader('X-Service-Token', 'test-token')->getJson('/api/hr/unknown-resource');

        $response->assertStatus(404);
    }

    public function test_alias_read_only_resource_route_is_removed_after_gateway_deprecation(): void
    {
        $response = $this->withHeader('X-Service-Token', 'test-token')->postJson('/api/attendance/attendance-summaries', [
            'MaNV' => 'NV01',
            'Thang' => 4,
            'Nam' => 2026,
        ]);

        $response->assertStatus(405);
    }
}
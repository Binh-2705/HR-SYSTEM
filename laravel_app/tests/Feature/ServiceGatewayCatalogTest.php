<?php

namespace Tests\Feature;

use Tests\TestCase;

class ServiceGatewayCatalogTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['services.service_gateway.token' => 'test-token']);
    }

    public function test_catalog_endpoint_is_removed_after_gateway_deprecation(): void
    {
        $response = $this->withHeader('X-Service-Token', 'test-token')->getJson('/api/services');

        $response->assertStatus(404);
    }
}
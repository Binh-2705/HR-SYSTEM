<?php

namespace Tests\Feature;

use Tests\TestCase;

class ServiceGatewayNotFoundTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['services.service_gateway.token' => 'test-token']);
    }

    public function test_unknown_service_returns_not_found(): void
    {
        $response = $this->withHeader('X-Service-Token', 'test-token')->getJson('/api/services/unknown/employees');

        $response->assertStatus(404);
    }

    public function test_missing_token_still_returns_not_found_when_gateway_removed(): void
    {
        $response = $this->getJson('/api/services');

        $response->assertStatus(404);
    }
}
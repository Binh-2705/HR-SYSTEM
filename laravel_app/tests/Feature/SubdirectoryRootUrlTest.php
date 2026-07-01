<?php

namespace Tests\Feature;

use Tests\TestCase;

class SubdirectoryRootUrlTest extends TestCase
{
    public function test_login_page_generates_asset_and_form_urls_with_subdirectory_prefix(): void
    {
        $response = $this
            ->withServerVariables([
                'HTTP_HOST' => 'localhost',
                'SCRIPT_NAME' => '/du_an2/index.php',
                'PHP_SELF' => '/du_an2/index.php',
            ])
            ->get('/login');

        $response->assertOk();
        $response->assertSee('http://localhost/du_an2/assets/css/style1.css?v=20260420-2', false);
        $response->assertSee('http://localhost/du_an2/assets/css/legacy-bridge.css?v=20260410-1', false);
        $response->assertSee('action="http://localhost/du_an2/login"', false);
        $response->assertSee('href="http://localhost/du_an2/forgot-password"', false);
    }

    public function test_root_redirect_uses_subdirectory_prefixed_login_url(): void
    {
        $response = $this
            ->withServerVariables([
                'HTTP_HOST' => 'localhost',
                'SCRIPT_NAME' => '/du_an2/index.php',
                'PHP_SELF' => '/du_an2/index.php',
            ])
            ->get('/');

        $response->assertRedirect('http://localhost/du_an2/login');
    }
}
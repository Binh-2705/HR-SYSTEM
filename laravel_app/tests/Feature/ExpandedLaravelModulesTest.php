<?php

namespace Tests\Feature;

use App\Services\GenericResourceModuleService;
use App\Services\PermissionService;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class ExpandedLaravelModulesTest extends TestCase
{
    public function test_generic_positions_module_renders_for_authorized_session(): void
    {
        $this->mock(PermissionService::class, fn ($mock) => $mock->shouldReceive('hasPermission', 'hasPermissionFromCache')->andReturnTrue());
        $this->mock(GenericResourceModuleService::class, function ($mock) {
            $mock->shouldReceive('describe')->once()->with('positions')->andReturn([
                'module' => config('laravel_resource_modules.positions'),
                'resource' => [
                    'primary_key' => 'MaCV',
                    'read_only' => false,
                    'columns' => [
                        ['field' => 'MaCV', 'type' => 'int', 'nullable' => false, 'extra' => ''],
                        ['field' => 'TenCV', 'type' => 'varchar(100)', 'nullable' => false, 'extra' => ''],
                    ],
                ],
            ]);
            $mock->shouldReceive('paginate')->once()->with('positions', [])->andReturn(new LengthAwarePaginator([
                (object) ['MaCV' => 1, 'TenCV' => 'Truong phong', '__resource_id' => '1'],
            ], 1, 12));
        });

        $this->withSession(['MaTK' => 3])->get('/positions')->assertOk()->assertSee('Truong phong');
    }

    public function test_search_page_renders_in_laravel_for_authenticated_session(): void
    {
        $this->mock(PermissionService::class, fn ($mock) => $mock->shouldReceive('hasPermission', 'hasPermissionFromCache')->andReturnTrue());

        $this->withSession(['MaTK' => 3])->get('/search')->assertOk()->assertSee('search', false);
    }

    public function test_hyphenated_generic_module_renders_with_dedicated_view_folder(): void
    {
        $this->mock(PermissionService::class, fn ($mock) => $mock->shouldReceive('hasPermission', 'hasPermissionFromCache')->andReturnTrue());
        $this->mock(GenericResourceModuleService::class, function ($mock) {
            $mock->shouldReceive('describe')->once()->with('salary-bands')->andReturn([
                'module' => config('laravel_resource_modules.salary-bands'),
                'resource' => [
                    'primary_key' => 'MaNgach',
                    'read_only' => false,
                    'columns' => [
                        ['field' => 'MaNgach', 'type' => 'int', 'nullable' => false, 'extra' => ''],
                        ['field' => 'TenNgach', 'type' => 'varchar(100)', 'nullable' => false, 'extra' => ''],
                    ],
                ],
            ]);
            $mock->shouldReceive('paginate')->once()->with('salary-bands', [])->andReturn(new LengthAwarePaginator([
                (object) ['MaNgach' => 1, 'TenNgach' => 'Chuyen vien', '__resource_id' => '1'],
            ], 1, 12));
        });

        $this->withSession(['MaTK' => 3])->get('/salary-bands')->assertOk()->assertSee('Chuyen vien');
    }

    public function test_legacy_alias_route_renders_generic_module(): void
    {
        $this->mock(PermissionService::class, fn ($mock) => $mock->shouldReceive('hasPermission', 'hasPermissionFromCache')->andReturnTrue());
        $this->mock(GenericResourceModuleService::class, function ($mock) {
            $mock->shouldReceive('describe')->once()->with('positions')->andReturn([
                'module' => config('laravel_resource_modules.positions'),
                'resource' => [
                    'primary_key' => 'MaCV',
                    'read_only' => false,
                    'columns' => [
                        ['field' => 'MaCV', 'type' => 'int', 'nullable' => false, 'extra' => ''],
                        ['field' => 'TenCV', 'type' => 'varchar(100)', 'nullable' => false, 'extra' => ''],
                    ],
                ],
            ]);
            $mock->shouldReceive('paginate')->once()->with('positions', [])->andReturn(new LengthAwarePaginator([
                (object) ['MaCV' => 5, 'TenCV' => 'Pho phong', '__resource_id' => '5'],
            ], 1, 12));
        });

        $this->withSession(['MaTK' => 3])->get('/chucvu')->assertOk()->assertSee('Pho phong');
    }
}
<?php

namespace App\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class GenericResourceModuleService
{
    public function __construct(
        private InternalApiClient $client,
        private ServiceResourceGateway $gateway,
    ) {
    }

    public function module(string $module): array
    {
        $config = config("laravel_resource_modules.{$module}");
        abort_unless(is_array($config), 404);

        return $config;
    }

    public function describe(string $module): array
    {
        $version = $this->getModuleCacheVersion($module);

        return Cache::remember(
            sprintf('resource_module_describe_%s_v%d', $module, $version),
            120,
            function () use ($module) {
                $moduleConfig = $this->module($module);
                $resourceConfig = $this->gateway->describeResource(
                    (string) ($moduleConfig['service'] ?? ''),
                    (string) ($moduleConfig['resource'] ?? $module)
                );
                $resourceConfig['read_only'] = (bool) (($moduleConfig['read_only'] ?? false) || ($resourceConfig['read_only'] ?? false));

                return [
                    'module' => $moduleConfig,
                    'resource' => $resourceConfig,
                ];
            }
        );
    }

    public function paginate(string $module, array $filters = [], int $perPage = 12): LengthAwarePaginator
    {
        $moduleConfig = $this->module($module);
        $currentPage = max(1, (int) request()->query('page', 1));
        $query = array_filter([
            'page' => $currentPage,
            'limit' => $perPage,
            'q' => trim((string) ($filters['q'] ?? '')),
            'ma_nv' => isset($filters['ma_nv']) ? (int) $filters['ma_nv'] : null,
        ], fn ($v) => $v !== null && $v !== '');

        $version = $this->getModuleCacheVersion($module);
        $cacheFingerprint = md5(json_encode($query));
        $response = Cache::remember(
            sprintf('resource_module_page_%s_v%d_%s', $module, $version, $cacheFingerprint),
            45,
            fn () => $this->gateway->listRecords(
                (string) ($moduleConfig['service'] ?? ''),
                (string) ($moduleConfig['resource'] ?? $module),
                $currentPage,
                $perPage,
                (string) ($query['q'] ?? ''),
                ['ma_nv' => $query['ma_nv'] ?? null]
            )
        );

        $items = collect($response['data'] ?? [])->map(fn ($item) => (object) $item);
        $pagination = (array) ($response['pagination'] ?? []);

        return new Paginator(
            $items,
            (int) ($pagination['total'] ?? 0),
            (int) ($pagination['limit'] ?? $perPage),
            (int) ($pagination['page'] ?? $currentPage),
            ['path' => Paginator::resolveCurrentPath(), 'pageName' => 'page', 'query' => request()->query()]
        );
    }

    public function find(string $module, string $id): ?array
    {
        $moduleConfig = $this->module($module);
        try {
            $payload = $this->gateway->getRecordOrNull(
                (string) ($moduleConfig['service'] ?? ''),
                (string) ($moduleConfig['resource'] ?? $module),
                $id
            );
        } catch (ModelNotFoundException) {
            return null;
        }

        return $payload['data'] ?? null;
    }

    public function create(string $module, array $payload): string
    {
        $moduleConfig = $this->module($module);

        $created = $this->gateway->createRecord(
            (string) ($moduleConfig['service'] ?? ''),
            (string) ($moduleConfig['resource'] ?? $module),
            $payload
        );

        $this->bumpModuleCacheVersion($module);

        return (string) ($created['record_id'] ?? data_get($created, 'data.__resource_id', ''));
    }

    public function update(string $module, string $id, array $payload): void
    {
        $moduleConfig = $this->module($module);

        $this->gateway->updateRecord(
            (string) ($moduleConfig['service'] ?? ''),
            (string) ($moduleConfig['resource'] ?? $module),
            $id,
            $payload
        );
        $this->bumpModuleCacheVersion($module);
    }

    public function delete(string $module, string $id): void
    {
        $moduleConfig = $this->module($module);

        $this->gateway->deleteRecord(
            (string) ($moduleConfig['service'] ?? ''),
            (string) ($moduleConfig['resource'] ?? $module),
            $id
        );
        $this->bumpModuleCacheVersion($module);
    }

    public function exportRows(string $module, array $filters = []): array
    {
        $meta = $this->describe($module);
        $query = ['q' => trim((string) ($filters['q'] ?? ''))];
        $version = $this->getModuleCacheVersion($module);
        $cacheFingerprint = md5(json_encode($query));
        $response = Cache::remember(
            sprintf('resource_module_export_%s_v%d_%s', $module, $version, $cacheFingerprint),
            60,
            fn () => $this->gateway->exportRecords(
                (string) ($meta['module']['service'] ?? ''),
                (string) ($meta['module']['resource'] ?? $module),
                $query['q'] ?? ''
            )
        );

        return [
            'meta' => $meta,
            'columns' => collect($response['columns'] ?? [])->filter(fn ($field) => $field !== '__resource_id')->values()->all(),
            'rows' => collect($response['rows'] ?? [])->map(fn ($row) => (array) $row),
        ];
    }

    private function getModuleCacheVersion(string $module): int
    {
        $key = sprintf('resource_module_version_%s', $module);

        if (!Cache::has($key)) {
            Cache::forever($key, 1);
            return 1;
        }

        return max(1, (int) Cache::get($key, 1));
    }

    private function bumpModuleCacheVersion(string $module): void
    {
        $key = sprintf('resource_module_version_%s', $module);

        if (!Cache::has($key)) {
            Cache::forever($key, 2);
            return;
        }

        Cache::increment($key);
    }
}
<?php

namespace App\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class DepartmentDirectoryService
{
    public function __construct(private InternalApiClient $client) {}

    public function paginate(array $filters = [], int $perPage = 12): LengthAwarePaginator
    {
        try {
            return $this->client->paginate('biz/departments/paginate', [
                'filters' => $filters,
                'perPage' => $perPage,
                'page'    => request()->input('page', 1),
            ]);
        } catch (RuntimeException) {
            return $this->emptyPaginator($perPage);
        }
    }

    public function find(int $departmentId): ?array
    {
        try {
            return $this->client->get("biz/departments/{$departmentId}")['data'] ?? null;
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return null;
        } catch (RuntimeException) {
            return null;
        }
    }

    public function create(array $payload): int
    {
        return (int) ($this->client->post('biz/departments', $payload)['id'] ?? 0);
    }

    public function update(int $departmentId, array $payload): void
    {
        $this->client->put("biz/departments/{$departmentId}", $payload);
    }

    public function delete(int $departmentId): void
    {
        $this->client->delete("biz/departments/{$departmentId}");
    }

    public function exportRows(array $filters = []): array
    {
        try {
            return $this->client->get('biz/departments/export', $filters)['data'] ?? [];
        } catch (RuntimeException) {
            return [];
        }
    }

    public function import(array $rows): array
    {
        try {
            return $this->client->post('biz/departments/import', ['rows' => $rows]);
        } catch (RuntimeException) {
            return [];
        }
    }

    public function importRows(array $rows): int
    {
        $response = $this->import($rows);

        return (int) ($response['count'] ?? 0);
    }

    private function emptyPaginator(int $perPage): LengthAwarePaginator
    {
        return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage, 1, [
            'path' => request()->url(),
            'query' => request()->query(),
        ]);
    }
}

<?php

namespace App\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ReportService
{
    public function __construct(private InternalApiClient $client) {}

    public function paginate(array $filters = [], int $perPage = 12): LengthAwarePaginator
    {
        try {
            return $this->client->paginate('biz/reports/paginate', [
                'filters' => $filters, 'perPage' => $perPage, 'page' => request()->input('page', 1),
            ]);
        } catch (RuntimeException) {
            return $this->emptyPaginator($perPage);
        }
    }

    public function find(int $reportId): ?array
    {
        try { return $this->client->get("biz/reports/{$reportId}")['data'] ?? null; }
        catch (\Illuminate\Database\Eloquent\ModelNotFoundException) { return null; }
        catch (RuntimeException) { return null; }
    }

    public function create(array $payload): int
    {
        return (int) ($this->client->post('biz/reports', $payload)['id'] ?? 0);
    }

    public function update(int $reportId, array $payload): void
    {
        $this->client->put("biz/reports/{$reportId}", $payload);
    }

    public function delete(int $reportId): void
    {
        $this->client->delete("biz/reports/{$reportId}");
    }

    public function exportRows(array $filters = []): array
    {
        try {
            return $this->client->get('biz/reports/export', $filters)['data'] ?? [];
        } catch (RuntimeException) {
            return [];
        }
    }

    private function emptyPaginator(int $perPage): LengthAwarePaginator
    {
        return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage, 1, [
            'path' => request()->url(),
            'query' => request()->query(),
        ]);
    }
}

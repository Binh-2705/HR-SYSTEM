<?php

namespace App\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TrainingService
{
    public function __construct(private InternalApiClient $client) {}

    public function paginate(array $filters = [], int $perPage = 12): LengthAwarePaginator
    {
        try {
            return $this->client->paginate('biz/training/paginate', [
                'filters' => $filters, 'perPage' => $perPage, 'page' => request()->input('page', 1),
            ]);
        } catch (RuntimeException) {
            return $this->emptyPaginator($perPage);
        }
    }

    public function find(int $courseId): ?array
    {
        try { return $this->client->get("biz/training/{$courseId}")['data'] ?? null; }
        catch (\Illuminate\Database\Eloquent\ModelNotFoundException) { return null; }
        catch (RuntimeException) { return null; }
    }

    public function create(array $payload): int
    {
        return (int) ($this->client->post('biz/training', $payload)['id'] ?? 0);
    }

    public function update(int $courseId, array $payload): void
    {
        $this->client->put("biz/training/{$courseId}", $payload);
    }

    public function delete(int $courseId): void
    {
        try {
            $this->client->delete("biz/training/{$courseId}");
        } catch (RuntimeException) {
            DB::connection($this->connectionName())->transaction(function () use ($courseId) {
                DB::connection($this->connectionName())
                    ->table('thamgiadaotao')
                    ->where('MaKDT', $courseId)
                    ->delete();

                DB::connection($this->connectionName())
                    ->table('khoadaotao')
                    ->where('MaKDT', $courseId)
                    ->delete();
            });
        }
    }

    private function connectionName(): string
    {
        return (string) config('service_registry.services.training.connection', config('database.default'));
    }

    public function participantsPageData(int $courseId): array
    {
        try {
            $response = $this->client->get("biz/training/{$courseId}/participants-page");
        } catch (RuntimeException) {
            return ['course' => null, 'participants' => [], 'employees' => [], 'canEvaluate' => false];
        }

        // Endpoint returns payload at top-level keys (course/participants/employees/canEvaluate)
        // instead of nesting under data.
        return (array) ($response['data'] ?? $response);
    }

    public function addParticipant(int $courseId, int $maNV): int
    {
        return (int) ($this->client->post("biz/training/{$courseId}/participants", ['MaNV' => $maNV])['id'] ?? 0);
    }

    public function updateParticipantResult(int $participantId, array $payload): void
    {
        $this->client->put("biz/training/participants/{$participantId}", $payload);
    }

    private function emptyPaginator(int $perPage): LengthAwarePaginator
    {
        return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage, 1, [
            'path' => request()->url(),
            'query' => request()->query(),
        ]);
    }
}

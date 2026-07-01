<?php

namespace App\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as PaginationLengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RecruitmentService
{
    public function __construct(private InternalApiClient $client) {}

    // ─── Campaigns ─────────────────────────────────────────────────────────────

    public function paginate(array $filters = [], int $perPage = 12): LengthAwarePaginator
    {
        try {
            return $this->client->paginate('biz/recruitment/paginate', [
                'filters' => $filters, 'perPage' => $perPage, 'page' => request()->input('page', 1),
            ]);
        } catch (RuntimeException) {
            return $this->paginateFromDatabase($filters, $perPage);
        }
    }

    public function find(int $id): ?array
    {
        try { return $this->client->get("biz/recruitment/{$id}")['data'] ?? null; }
        catch (\Illuminate\Database\Eloquent\ModelNotFoundException) { return null; }
        catch (RuntimeException) {
            $record = DB::connection($this->connectionName())
                ->table('dottuyendung')
                ->where('MaDTD', $id)
                ->first();

            return $record ? (array) $record : null;
        }
    }

    public function create(array $payload): int
    {
        return (int) ($this->client->post('biz/recruitment', $payload)['id'] ?? 0);
    }

    public function update(int $id, array $payload): void
    {
        $this->client->put("biz/recruitment/{$id}", $payload);
    }

    public function delete(int $id): void
    {
        try {
            $this->client->delete("biz/recruitment/{$id}");
        } catch (RuntimeException) {
            DB::connection($this->connectionName())
                ->table('dottuyendung')
                ->where('MaDTD', $id)
                ->delete();
        }
    }

    private function paginateFromDatabase(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = DB::connection($this->connectionName())
            ->table('dottuyendung as d')
            ->leftJoin('hosoungtuyen as h', 'h.MaDTD', '=', 'd.MaDTD')
            ->selectRaw('d.MaDTD, d.TenDotTuyenDung, d.ViTriTuyenDung, d.SoLuong, d.TrangThai, COUNT(h.MaHS) as SoHoSo')
            ->groupBy('d.MaDTD', 'd.TenDotTuyenDung', 'd.ViTriTuyenDung', 'd.SoLuong', 'd.TrangThai')
            ->orderByDesc('d.MaDTD');

        $keyword = trim((string) ($filters['q'] ?? ''));
        if ($keyword !== '') {
            $query->where(function ($inner) use ($keyword) {
                $inner->where('d.TenDotTuyenDung', 'like', '%' . $keyword . '%')
                    ->orWhere('d.ViTriTuyenDung', 'like', '%' . $keyword . '%');
            });
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $query->where('d.TrangThai', $status);
        }

        $page = max(1, (int) request()->input('page', 1));
        $total = (clone $query)->count();
        $items = $query->forPage($page, $perPage)->get()->map(function ($row) {
            return (object) [
                'MaDTD' => $row->MaDTD,
                'TenDotTuyenDung' => $row->TenDotTuyenDung,
                'ViTriTuyenDung' => $row->ViTriTuyenDung,
                'SoLuong' => $row->SoLuong,
                'SoHoSo' => (int) ($row->SoHoSo ?? 0),
                'TrangThai' => $row->TrangThai,
            ];
        });

        return new PaginationLengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    private function connectionName(): string
    {
        return (string) config('service_registry.services.recruitment.connection', config('database.default'));
    }

    public function campaignOptions(): array
    {
        try {
            $data = $this->client->get('biz/recruitment/campaign-options')['data'] ?? [];
            return array_map(fn($c) => (object) $c, $data);
        } catch (RuntimeException) {
            return [];
        }
    }

    // ─── Candidates ────────────────────────────────────────────────────────────

    public function paginateCandidates(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        try {
            return $this->client->paginate('biz/recruitment/candidates/paginate', [
                'filters' => $filters, 'perPage' => $perPage, 'page' => request()->input('page', 1),
            ]);
        } catch (RuntimeException) {
            return $this->emptyPaginator($perPage);
        }
    }

    public function findCandidate(int $id): ?array
    {
        try { return $this->client->get("biz/recruitment/candidates/{$id}")['data'] ?? null; }
        catch (\Illuminate\Database\Eloquent\ModelNotFoundException) { return null; }
    }

    public function createCandidate(array $payload): int
    {
        return (int) ($this->client->post('biz/recruitment/candidates', $payload)['id'] ?? 0);
    }

    // ─── Applications ──────────────────────────────────────────────────────────

    public function paginateApplications(int $campaignId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        try {
            return $this->client->paginate("biz/recruitment/{$campaignId}/applications/paginate", [
                'filters' => $filters, 'perPage' => $perPage, 'page' => request()->input('page', 1),
            ]);
        } catch (RuntimeException) {
            return $this->emptyPaginator($perPage);
        }
    }

    public function attachCandidate(int $campaignId, array $payload): int
    {
        return (int) ($this->client->post("biz/recruitment/{$campaignId}/applications", $payload)['id'] ?? 0);
    }

    public function findApplication(int $id): ?array
    {
        try { return $this->client->get("biz/recruitment/applications/{$id}")['data'] ?? null; }
        catch (\Illuminate\Database\Eloquent\ModelNotFoundException) { return null; }
    }

    public function updateApplicationStatus(int $id, string $status): void
    {
        $this->client->put("biz/recruitment/applications/{$id}/status", ['TrangThai' => $status]);
    }

    public function updateKanban(int $id, array $payload): void
    {
        $this->client->put("biz/recruitment/applications/{$id}/kanban", $payload);
    }

    // ─── Interviews ────────────────────────────────────────────────────────────

    public function listInterviews(int $applicationId): array
    {
        try {
            return $this->client->get("biz/recruitment/applications/{$applicationId}/interviews")['data'] ?? [];
        } catch (RuntimeException) {
            return [];
        }
    }

    public function listReviews(int $applicationId): array
    {
        try {
            return $this->client->get("biz/recruitment/applications/{$applicationId}/reviews")['data'] ?? [];
        } catch (\RuntimeException) {
            return [];
        }
    }

    public function storeInterview(int $applicationId, array $payload): int
    {
        return (int) ($this->client->post("biz/recruitment/applications/{$applicationId}/interviews", $payload)['id'] ?? 0);
    }

    public function storeReview(int $interviewId, array $payload): int
    {
        return (int) ($this->client->post("biz/recruitment/interviews/{$interviewId}/reviews", $payload)['id'] ?? 0);
    }

    private function emptyPaginator(int $perPage): LengthAwarePaginator
    {
        return new PaginationLengthAwarePaginator([], 0, $perPage, 1, [
            'path' => request()->url(),
            'query' => request()->query(),
        ]);
    }
}

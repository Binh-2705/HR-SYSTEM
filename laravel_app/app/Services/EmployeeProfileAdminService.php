<?php

namespace App\Services;

class EmployeeProfileAdminService
{
    private InternalApiClient $client;

    public function __construct(InternalApiClient $client)
    {
        $this->client = $client;
    }

    public function profileDetail(int $profileId): ?array
    {
        try { return $this->client->get("biz/employee-profiles/{$profileId}")['data'] ?? null; }
        catch (\Illuminate\Database\Eloquent\ModelNotFoundException) { return null; }
    }

    public function pendingRequests(): array
    {
        return $this->client->get('biz/employee-profiles/pending-requests')['data'] ?? [];
    }

    public function resolveRequest(int $requestId, string $decision, int $reviewedBy, string $reviewNote = ''): void
    {
        $this->client->post("biz/employee-profiles/requests/{$requestId}/resolve", [
            'decision'    => $decision,
            'reviewed_by' => $reviewedBy,
            'review_note' => $reviewNote,
        ]);
    }

    public function employeeInfo(int $employeeId): ?array
    {
        try { return $this->client->get("biz/employee-profiles/employee/{$employeeId}/info")['data'] ?? null; }
        catch (\Illuminate\Database\Eloquent\ModelNotFoundException) { return null; }
    }
}

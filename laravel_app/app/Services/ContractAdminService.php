<?php

namespace App\Services;

class ContractAdminService
{
    public function __construct(private InternalApiClient $client) {}

    public function contractDetail(int $contractId): ?array
    {
        try { return $this->client->get("biz/contracts/{$contractId}")['data'] ?? null; }
        catch (\Illuminate\Database\Eloquent\ModelNotFoundException) { return null; }
    }

    public function salaryHistory(int $contractId): array
    {
        return $this->client->get("biz/contracts/{$contractId}/salary-history")['data'] ?? [];
    }

    public function renewContract(int $contractId, array $payload): void
    {
        $this->client->post("biz/contracts/{$contractId}/renew", $payload);
    }

    public function terminateContract(int $contractId): void
    {
        $this->client->post("biz/contracts/{$contractId}/terminate");
    }
}

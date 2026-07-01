<?php

namespace App\Services;

class AppAuditLogService
{
    public function __construct(private InternalApiClient $client) {}

    public function readFilteredRows(string $levelFilter = '', string $q = ''): array
    {
        return $this->client->get('biz/audit-log', array_filter([
            'level' => $levelFilter,
            'q'     => $q,
        ]))['data'] ?? [];
    }
}

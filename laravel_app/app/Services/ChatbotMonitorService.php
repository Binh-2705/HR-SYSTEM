<?php

namespace App\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ChatbotMonitorService
{
    public function __construct(private InternalApiClient $client) {}

    public function paginateSessions(int $perPage = 15): LengthAwarePaginator
    {
        return $this->client->paginate('biz/chatbot/paginate', [
            'perPage' => $perPage,
            'page'    => request()->input('page', 1),
        ]);
    }

    public function findSession(int $sessionId): array
    {
        return $this->client->get("biz/chatbot/{$sessionId}");
    }
}

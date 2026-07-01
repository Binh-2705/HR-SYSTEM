<?php

namespace App\Http\Resources\Api\Chatbot;

use Illuminate\Http\Resources\Json\JsonResource;

class ChatbotSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $row = (array) $this->resource;

        return [
            'id' => $row['id'] ?? null,
            'session_key' => $row['session_key'] ?? null,
            'ma_tk' => $row['ma_tk'] ?? null,
            'username' => $row['username'] ?? null,
            'role_name' => $row['role_name'] ?? null,
            'created_at' => $row['created_at'] ?? null,
            'last_interaction_at' => $row['last_interaction_at'] ?? null,
            'MessageCount' => $row['MessageCount'] ?? null,
            'DraftCount' => $row['DraftCount'] ?? null,
            '__resource_id' => isset($row['id']) ? (string) $row['id'] : null,
        ];
    }
}

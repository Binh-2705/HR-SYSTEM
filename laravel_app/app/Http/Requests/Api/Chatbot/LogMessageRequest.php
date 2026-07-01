<?php

namespace App\Http\Requests\Api\Chatbot;

use Illuminate\Foundation\Http\FormRequest;

class LogMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'session_id' => ['required', 'integer', 'min:1'],
            'role_name' => ['required', 'string', 'max:100'],
            'content' => ['nullable', 'string'],
            'source_name' => ['nullable', 'string', 'max:100'],
            'actions' => ['nullable', 'array'],
            'suggestions' => ['nullable', 'array'],
            'action_draft_token' => ['nullable', 'string', 'max:255'],
        ];
    }
}

<?php

namespace App\Http\Requests\Api\Chatbot;

use Illuminate\Foundation\Http\FormRequest;

class CreateDraftRequest extends FormRequest
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
            'created_by' => ['required', 'integer', 'min:1'],
            'draft' => ['required', 'array'],
            'draft.action_type' => ['nullable', 'string', 'max:100'],
            'draft.title' => ['nullable', 'string', 'max:255'],
            'draft.summary' => ['nullable', 'string'],
            'draft.required_permission' => ['nullable', 'string', 'max:255'],
            'draft.payload' => ['nullable', 'array'],
        ];
    }
}

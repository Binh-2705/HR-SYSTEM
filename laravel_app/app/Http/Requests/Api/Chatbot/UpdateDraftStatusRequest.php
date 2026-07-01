<?php

namespace App\Http\Requests\Api\Chatbot;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDraftStatusRequest extends FormRequest
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
            'status_name' => ['sometimes', 'string', 'max:50'],
            'confirmed' => ['sometimes', 'boolean'],
            'executed_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}

<?php

namespace App\Http\Requests\Api\Chatbot;

use Illuminate\Foundation\Http\FormRequest;

class UpsertSessionRequest extends FormRequest
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
            'session_key' => ['required', 'string', 'max:191'],
            'ma_tk' => ['nullable', 'integer', 'min:0'],
            'username' => ['nullable', 'string', 'max:255'],
            'role_name' => ['nullable', 'string', 'max:100'],
        ];
    }
}

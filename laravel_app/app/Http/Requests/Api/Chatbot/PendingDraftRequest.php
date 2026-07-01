<?php

namespace App\Http\Requests\Api\Chatbot;

use Illuminate\Foundation\Http\FormRequest;

class PendingDraftRequest extends FormRequest
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
            'token' => ['required', 'string', 'max:255'],
            'account_id' => ['required', 'integer', 'min:1'],
        ];
    }
}

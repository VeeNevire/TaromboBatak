<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AskTaromboRequest extends FormRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'question' => ['required', 'string', 'max:4000'],
            'conversation_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}

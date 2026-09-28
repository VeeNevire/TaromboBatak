<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class MargaNewsTopicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'keyword' => ['required', 'string', 'max:150'],
            'marga_id' => ['nullable', 'integer', 'exists:margas,id'],
            'is_active' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}

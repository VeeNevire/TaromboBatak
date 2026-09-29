<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMargaNewsAutomationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'interval_minutes' => ['required', 'integer', 'min:1', 'max:10080'],
            'prompt' => ['required_if:enabled,true', 'nullable', 'string', 'max:20000'],
        ];
    }
}

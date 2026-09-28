<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IngestMargaNewsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The agent token is checked by AuthenticateMargaNewsAgent.
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'agent' => ['nullable', 'string', 'max:60'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.topic_id' => ['nullable', 'integer'],
            'items.*.title' => ['required', 'string', 'max:500'],
            'items.*.url' => ['required', 'string', 'max:2048', 'url:http,https'],
            'items.*.publisher' => ['nullable', 'string', 'max:120'],
            'items.*.published_at' => ['nullable', 'string', 'max:60'],
            'items.*.excerpt' => ['nullable', 'string', 'max:5000'],
            'items.*.summary' => ['nullable', 'string', 'max:5000'],
            'items.*.margas' => ['nullable', 'array', 'max:20'],
            'items.*.margas.*' => ['string', 'max:100'],
        ];
    }
}

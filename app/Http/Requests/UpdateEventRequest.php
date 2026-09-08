<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEventRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'related_marga_ids' => ['sometimes', 'array', 'min:1'],
            'related_marga_ids.*' => ['required', 'integer', 'distinct', 'exists:margas,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'registration_url' => ['nullable', 'url', 'max:500'],
            'date' => ['required', 'date'],
            'published' => ['boolean'],
            'marga_id' => [
                Rule::requiredIf(fn () => $this->user()?->isStaff() && ! $this->has('related_marga_ids')),
                'nullable',
                'integer',
                'exists:margas,id',
            ],
        ];
    }
}

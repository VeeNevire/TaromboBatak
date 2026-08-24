<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'registration_url' => ['nullable', 'url', 'max:500'],
            'date' => ['required', 'date'],
            'published' => ['boolean'],
            'marga_id' => [
                Rule::requiredIf(fn () => $this->user()?->isStaff() ?? false),
                'nullable',
                'integer',
                'exists:margas,id',
            ],
        ];
    }
}

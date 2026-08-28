<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStoryRequest extends FormRequest
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
            'image' => ['nullable', 'url'],
            'content_url' => ['nullable', 'url:http,https', 'max:2048'],
            'published' => ['boolean'],
            'classification' => ['required', Rule::in(['umum', 'marga'])],
            'marga_id' => [
                Rule::requiredIf(fn () => $this->user()?->isStaff() && $this->input('classification') === 'marga'),
                'nullable',
                'integer',
                'exists:margas,id',
            ],
        ];
    }
}

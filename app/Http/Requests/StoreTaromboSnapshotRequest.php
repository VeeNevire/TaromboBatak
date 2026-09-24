<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaromboSnapshotRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png', 'mimetypes:image/jpeg,image/png', 'max:10240'],
            'view' => ['required', 'string', Rule::in(['diagram', 'tree'])],
            'center_person_id' => ['nullable', 'integer', 'exists:people,id'],
            'title' => ['nullable', 'string', 'max:120'],
            'resolution' => ['nullable', 'integer', Rule::in([360, 480, 720, 1080, 1440, 2160, 4320])],
            'paper_size' => ['nullable', 'string', Rule::in(['A4', 'A3', 'A2', 'A1', 'A0'])],
            'included_person_ids' => ['nullable', 'array'],
            'included_person_ids.*' => ['integer', 'exists:people,id'],
        ];
    }
}

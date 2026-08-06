<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePersonRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'alias' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', 'string', 'max:1'],
            'marga_id' => ['nullable', 'exists:margas,id'],
            'father_id' => ['nullable', 'exists:people,id'],
            'mother_id' => ['nullable', 'exists:people,id'],
            'birth_order' => ['nullable', 'integer', 'min:1'],
            'sibling_count' => ['nullable', 'integer', 'min:1'],
            'nomor' => ['nullable', 'string', 'max:50', Rule::unique('people', 'nomor')],
            'birth_year' => ['nullable', 'digits:4'],
            'death_year' => ['nullable', 'digits:4'],
            'image' => ['nullable', 'url'],
            'bio' => ['nullable', 'string'],
            'spouse' => ['nullable', 'string', 'max:255'],
            'spouse_marga' => ['nullable', 'string', 'max:255'],
            'father' => ['nullable', 'array'],
            'father.name' => ['nullable', 'string', 'max:255'],
            'father.birth_year' => ['nullable', 'digits:4'],
            'father.death_year' => ['nullable', 'digits:4'],
            'mother' => ['nullable', 'array'],
            'mother.name' => ['nullable', 'string', 'max:255'],
            'mother.birth_year' => ['nullable', 'digits:4'],
            'mother.death_year' => ['nullable', 'digits:4'],
            'children' => ['nullable', 'array'],
            'children.*.id' => ['nullable', 'exists:people,id'],
            'children.*.name' => ['nullable', 'string', 'max:255'],
            'children.*.gender' => ['nullable', 'string', 'max:1'],
            'children.*.spouse' => ['nullable', 'string', 'max:255'],
            'children.*.spouse_marga' => ['nullable', 'string', 'max:255'],
            'children.*.nomor' => ['nullable', 'string', 'max:50', Rule::unique('people', 'nomor')],
        ];
    }
}
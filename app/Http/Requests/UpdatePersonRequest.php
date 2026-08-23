<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePersonRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'alias' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', 'string', 'max:1'],
            'marga_id' => ['nullable', 'exists:margas,id'],
            'father_id' => ['nullable', 'exists:people,id'],
            'mother_id' => ['nullable', 'exists:people,id'],
            'birth_order' => ['nullable', 'integer', 'min:1'],
            'sibling_count' => ['nullable', 'integer', 'min:1'],
            'birth_year' => ['nullable', 'digits:4'],
            'death_year' => ['nullable', 'digits:4'],
            'image' => ['nullable', 'url'],
            'bio' => ['nullable', 'string'],
            'spouse' => ['nullable', 'string', 'max:255'],
            'spouse_marga' => ['nullable', 'string', 'max:255'],
            'is_public' => $this->user()?->isStaff() ? ['nullable', 'boolean'] : ['prohibited'],
            'father' => ['nullable', 'array'],
            'father.name' => ['nullable', 'string', 'max:255'],
            'father.alias' => ['nullable', 'string', 'max:255'],
            'father.marga_id' => ['nullable', 'exists:margas,id'],
            'father.new_marga' => ['nullable', 'string', 'max:255'],
            'father.birth_year' => ['nullable', 'digits:4'],
            'father.death_year' => ['nullable', 'digits:4'],
            'mother' => ['nullable', 'array'],
            'mother.name' => ['nullable', 'string', 'max:255'],
            'mother.alias' => ['nullable', 'string', 'max:255'],
            'mother.marga_id' => ['nullable', 'exists:margas,id'],
            'mother.new_marga' => ['nullable', 'string', 'max:255'],
            'mother.birth_year' => ['nullable', 'digits:4'],
            'mother.death_year' => ['nullable', 'digits:4'],
            'mothers' => ['nullable', 'array'],
            'mothers.*.name' => ['nullable', 'string', 'max:255'],
            'mothers.*.alias' => ['nullable', 'string', 'max:255'],
            'mothers.*.marga_id' => ['nullable', 'exists:margas,id'],
            'mothers.*.new_marga' => ['nullable', 'string', 'max:255'],
            'mothers.*.birth_year' => ['nullable', 'digits:4'],
            'mothers.*.death_year' => ['nullable', 'digits:4'],
            'children' => ['nullable', 'array'],
            'children.*.id' => ['nullable', 'exists:people,id'],
            'children.*.name' => ['nullable', 'string', 'max:255'],
            'children.*.alias' => ['nullable', 'string', 'max:255'],
            'children.*.gender' => ['nullable', 'string', 'max:1'],
            'children.*.marga_id' => ['nullable', 'exists:margas,id'],
            'children.*.new_marga' => ['nullable', 'string', 'max:255'],
            'children.*.spouse' => ['nullable', 'string', 'max:255'],
            'children.*.spouse_marga' => ['nullable', 'string', 'max:255'],
            'ownChildren' => ['nullable', 'array'],
            'ownChildren.*.id' => ['nullable', 'exists:people,id'],
            'ownChildren.*.name' => ['nullable', 'string', 'max:255'],
            'ownChildren.*.alias' => ['nullable', 'string', 'max:255'],
            'ownChildren.*.gender' => ['nullable', 'string', 'max:1'],
            'ownChildren.*.marga_id' => ['nullable', 'exists:margas,id'],
            'ownChildren.*.new_marga' => ['nullable', 'string', 'max:255'],
            'ownChildren.*.spouse' => ['nullable', 'string', 'max:255'],
            'ownChildren.*.spouse_marga' => ['nullable', 'string', 'max:255'],
            'removed_child_ids' => ['nullable', 'array'],
            'removed_child_ids.*' => ['integer', 'distinct', 'exists:people,id'],
            'removed_own_child_ids' => ['nullable', 'array'],
            'removed_own_child_ids.*' => ['integer', 'distinct', 'exists:people,id'],
        ];

        return $rules;
    }
}

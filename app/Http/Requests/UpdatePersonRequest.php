<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'image_mode' => ['nullable', Rule::in(['url', 'upload'])],
            'image' => ['nullable', 'url:http,https', 'max:2048'],
            'image_file' => [
                'nullable',
                Rule::prohibitedIf($this->input('image_mode') !== 'upload'),
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
            ],
            'bio' => ['nullable', 'string'],
            'related_stories' => ['nullable', 'array', 'max:10'],
            'related_stories.*.title' => ['nullable', 'required_with:related_stories.*.url', 'string', 'max:255'],
            'related_stories.*.url' => ['nullable', 'required_with:related_stories.*.title', 'url:http,https', 'max:2048', 'distinct'],
            'spouse' => ['nullable', 'string', 'max:255'],
            'spouse_marga' => ['nullable', 'string', 'max:255'],
            'is_public' => $this->user()?->isStaff() ? ['nullable', 'boolean'] : ['prohibited'],
            'version_tree' => ['nullable', 'integer', 'exists:family_trees,id'],
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
            'mothers.*.id' => ['nullable', 'exists:people,id'],
            'mothers.*.name' => ['nullable', 'string', 'max:255'],
            'mothers.*.alias' => ['nullable', 'string', 'max:255'],
            'mothers.*.marga_id' => ['nullable', 'exists:margas,id'],
            'mothers.*.new_marga' => ['nullable', 'string', 'max:255'],
            'mothers.*.birth_year' => ['nullable', 'digits:4'],
            'mothers.*.death_year' => ['nullable', 'digits:4'],
            'mothers.*.father_name' => ['nullable', 'string', 'max:255'],
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
            'ownChildren.*.mother_index' => ['nullable', 'integer', 'min:0'],
            'removed_child_ids' => ['nullable', 'array'],
            'removed_child_ids.*' => ['integer', 'distinct', 'exists:people,id'],
            'removed_own_child_ids' => ['nullable', 'array'],
            'removed_own_child_ids.*' => ['integer', 'distinct', 'exists:people,id'],
        ];

        return $rules;
    }
}

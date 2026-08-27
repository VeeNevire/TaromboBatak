<?php

namespace App\Http\Requests;

use App\Models\FamilyTree;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSharedFamilyTreePersonRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $familyTree = $this->route('familyTree');

        return $familyTree instanceof FamilyTree
            && $this->user()?->can('append', $familyTree) === true;
    }

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
            'gender' => ['nullable', Rule::in(['L', 'P'])],
            'father_node_id' => ['required', 'integer', 'exists:family_tree_nodes,id'],
            'mother_node_id' => ['nullable', 'integer', 'exists:family_tree_nodes,id'],
            'birth_order' => ['nullable', 'integer', 'min:1'],
            'birth_year' => ['nullable', 'digits:4'],
            'death_year' => ['nullable', 'digits:4'],
            'spouse' => ['nullable', 'string', 'max:255'],
            'spouse_marga' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:5000'],
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Models\FamilyTree;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'children' => ['nullable', 'array', 'max:20'],
            'children.*.name' => ['required', 'string', 'max:255'],
            'children.*.alias' => ['nullable', 'string', 'max:255'],
            'children.*.gender' => ['nullable', Rule::in(['L', 'P'])],
            'children.*.birth_year' => ['nullable', 'digits:4'],
            'children.*.death_year' => ['nullable', 'digits:4'],
            'children.*.spouse' => ['nullable', 'string', 'max:255'],
            'children.*.spouse_marga' => ['nullable', 'string', 'max:255'],
            'children.*.bio' => ['nullable', 'string', 'max:5000'],
            'siblings' => ['nullable', 'array', 'max:20'],
            'siblings.*.name' => ['required', 'string', 'max:255'],
            'siblings.*.alias' => ['nullable', 'string', 'max:255'],
            'siblings.*.gender' => ['nullable', Rule::in(['L', 'P'])],
            'siblings.*.birth_year' => ['nullable', 'digits:4'],
            'siblings.*.death_year' => ['nullable', 'digits:4'],
            'siblings.*.spouse' => ['nullable', 'string', 'max:255'],
            'siblings.*.spouse_marga' => ['nullable', 'string', 'max:255'],
            'siblings.*.bio' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /** @return array<int, \Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $familyTree = $this->route('familyTree');

            if ($familyTree instanceof FamilyTree
                && $this->filled('father_node_id')
                && $this->filled('mother_node_id')) {
                $fatherNode = $familyTree->nodes()
                    ->with('person.wives:id')
                    ->find($this->integer('father_node_id'));
                $motherNode = $familyTree->nodes()
                    ->find($this->integer('mother_node_id'));

                if ($fatherNode !== null && $motherNode !== null
                    && ! $fatherNode->person->wives->contains('id', $motherNode->person_id)) {
                    $validator->errors()->add(
                        'mother_node_id',
                        'Ibu harus merupakan pasangan dari ayah yang dipilih.',
                    );
                }
            }

            if ($this->filled('children') && $this->input('gender') !== 'L') {
                $validator->errors()->add(
                    'children',
                    'Daftar anak hanya dapat ditambahkan saat anggota utama berjenis kelamin laki-laki.',
                );
            }
        }];
    }
}

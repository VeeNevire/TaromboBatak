<?php

namespace App\Http\Requests;

use App\Models\FamilyTree;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreFamilyTreeShareRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $familyTree = $this->route('familyTree');

        return $familyTree instanceof FamilyTree
            && $this->user()?->can('share', $familyTree) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['recipient_id' => ['required', 'integer', 'exists:users,id']];
    }
}

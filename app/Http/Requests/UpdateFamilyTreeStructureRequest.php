<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFamilyTreeStructureRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'entries' => ['required', 'array', 'min:1'],
            'entries.*.id' => ['required', 'integer', 'distinct'],
            'entries.*.father_node_id' => ['nullable', 'integer'],
            'entries.*.birth_order' => ['nullable', 'integer', 'min:1'],
        ];
    }
}

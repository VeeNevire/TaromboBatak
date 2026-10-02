<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFamilyTreeSiblingOrderRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'father_node_id' => ['required', 'integer'],
            'node_ids' => ['required', 'array', 'min:2'],
            'node_ids.*' => ['required', 'integer', 'distinct'],
        ];
    }
}

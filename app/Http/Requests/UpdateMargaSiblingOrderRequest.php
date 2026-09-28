<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMargaSiblingOrderRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'father_id' => ['required', 'integer', 'exists:people,id'],
            'person_ids' => ['required', 'array', 'min:2'],
            'person_ids.*' => ['required', 'integer', 'distinct', 'exists:people,id'],
        ];
    }
}

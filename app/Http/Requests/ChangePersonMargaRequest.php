<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChangePersonMargaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('person'));
    }

    public function rules(): array
    {
        return [
            'marga_id' => ['required', 'integer', 'exists:margas,id'],
            'include_descendants' => ['required', 'boolean'],
        ];
    }
}

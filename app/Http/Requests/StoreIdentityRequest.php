<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreIdentityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && ! $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'person_id' => ['required', 'integer', 'exists:people,id'],
        ];
    }
}

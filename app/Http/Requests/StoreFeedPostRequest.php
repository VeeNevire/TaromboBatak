<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFeedPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:2000'],
            'audience' => ['sometimes', 'required', 'in:public,marga'],
            'marga_ids' => ['exclude_unless:audience,marga', 'required', 'array', 'min:1'],
            'marga_ids.*' => ['required', 'integer', 'distinct', 'exists:margas,id'],
        ];
    }
}

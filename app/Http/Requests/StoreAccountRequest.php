<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreAccountRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'role' => ['required', Rule::in(['admin', 'subadmin', 'contributor_main', 'contributor_member', 'user'])],
            'marga_id' => ['nullable', 'exists:margas,id'],
            'managed_marga_ids' => ['required_if:role,contributor_main,contributor_member', 'array', 'min:1'],
            'managed_marga_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('margas', 'id')->whereNotNull('identity_person_id'),
            ],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }
}

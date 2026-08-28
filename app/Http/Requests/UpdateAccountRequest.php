<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateAccountRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $account = $this->route('account');
        $account = $account instanceof User ? $account : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($account)],
            'role' => ['required', Rule::in(['admin', 'subadmin', 'contributor_main', 'contributor_member', 'user'])],
            'marga_id' => ['nullable', 'exists:margas,id'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ];
    }
}

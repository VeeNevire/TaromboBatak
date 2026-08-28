<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'recipient_id' => [
                'required',
                'integer',
                Rule::exists(User::class, 'id')->where(fn ($query) => $query->where('role', '!=', 'admin')),
                Rule::notIn([$this->user()?->id]),
            ],
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AttachTaromboQrRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'token' => ['required', 'uuid', 'unique:tarombo_snapshots,qr_token'],
            'x' => ['required', 'numeric', 'between:0,100'],
            'y' => ['required', 'numeric', 'between:0,100'],
            'size' => ['required', 'numeric', 'between:10,30'],
        ];
    }
}

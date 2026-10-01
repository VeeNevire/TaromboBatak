<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListTaromboSnapshotsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'display' => ['nullable', 'in:images,titles'],
        ];
    }
}

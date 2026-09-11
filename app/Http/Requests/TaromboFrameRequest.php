<?php

namespace App\Http\Requests;

use App\Models\TaromboFrame;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TaromboFrameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'image' => [$this->route('taromboFrame') instanceof TaromboFrame ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg', 'mimetypes:image/jpeg', 'max:10240'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}

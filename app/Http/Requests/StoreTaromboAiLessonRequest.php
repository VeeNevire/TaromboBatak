<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaromboAiLessonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'marga_id' => ['nullable', 'integer', 'exists:margas,id'],
            'title' => ['required', 'string', 'max:160'],
            'topic' => ['required', 'string', 'max:100'],
            'content' => ['required', 'string', 'min:20', 'max:12000'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}

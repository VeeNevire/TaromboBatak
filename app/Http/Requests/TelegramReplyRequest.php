<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TelegramReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['body' => ['required', 'string', 'max:4096']];
    }
}

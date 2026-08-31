<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class TelegramPasswordRequest extends FormRequest
{
    public function authorize(): bool { return $this->user() !== null; }

    public function rules(): array { return ['password' => ['required', 'string', 'max:255']]; }
}

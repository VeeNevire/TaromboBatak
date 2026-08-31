<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class TelegramPhoneRequest extends FormRequest
{
    public function authorize(): bool { return $this->user() !== null; }

    public function rules(): array { return ['phone' => ['required', 'string', 'regex:/^\\+[1-9][0-9]{7,14}$/']]; }
}

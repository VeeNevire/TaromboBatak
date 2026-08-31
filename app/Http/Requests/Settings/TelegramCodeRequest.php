<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class TelegramCodeRequest extends FormRequest
{
    public function authorize(): bool { return $this->user() !== null; }

    public function rules(): array { return ['code' => ['required', 'string', 'regex:/^[0-9]{5,6}$/']]; }
}

<?php

namespace App\Http\Requests;

use App\Models\Marga;
use Illuminate\Foundation\Http\FormRequest;

class StoreMargaMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->route('marga') instanceof Marga;
    }

    public function rules(): array
    {
        return ['body' => ['required', 'string', 'max:2000']];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['body' => trim((string) $this->input('body'))]);
    }
}

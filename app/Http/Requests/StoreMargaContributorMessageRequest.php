<?php

namespace App\Http\Requests;

use App\Models\Marga;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreMargaContributorMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $marga = $this->route('marga');
        $contributor = $this->route('contributor');

        return $this->user() !== null
            && $marga instanceof Marga
            && $contributor instanceof User
            && $contributor->marga_id === $marga->id
            && in_array($contributor->role, ['contributor_main', 'contributor_member'], true);
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

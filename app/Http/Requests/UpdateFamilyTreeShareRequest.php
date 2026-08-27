<?php

namespace App\Http\Requests;

use App\Models\FamilyTreeShare;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFamilyTreeShareRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $share = $this->route('familyTreeShare');

        return $share instanceof FamilyTreeShare
            && $share->recipient_id === $this->user()?->id
            && $share->status === FamilyTreeShare::STATUS_PENDING;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['status' => ['required', Rule::in([
            FamilyTreeShare::STATUS_ACCEPTED,
            FamilyTreeShare::STATUS_REJECTED,
        ])]];
    }
}

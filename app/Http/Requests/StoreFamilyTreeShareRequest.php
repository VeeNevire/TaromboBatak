<?php

namespace App\Http\Requests;

use App\Models\FamilyTree;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreFamilyTreeShareRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $familyTree = $this->route('familyTree');

        return $familyTree instanceof FamilyTree
            && $this->user()?->can('share', $familyTree) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['recipient_id' => ['required', 'integer', 'exists:users,id']];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('recipient_id') || $this->user()?->isStaff()) {
                return;
            }

            $recipient = User::query()->find($this->integer('recipient_id'));

            if ($recipient?->marga_id !== $this->user()?->marga_id) {
                $validator->errors()->add(
                    'recipient_id',
                    'Silsilah hanya dapat dibagikan kepada akun dari marga yang sama.',
                );
            }
        }];
    }
}

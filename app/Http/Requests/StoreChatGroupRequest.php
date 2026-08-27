<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreChatGroupRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->canUseGroups() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'member_ids' => ['nullable', 'array', 'max:100'],
            'member_ids.*' => ['integer', 'distinct', Rule::exists(User::class, 'id')],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            /** @var User $user */
            $user = $this->user();

            User::query()->whereKey($this->memberIds())->get()
                ->each(function (User $member) use ($user, $validator): void {
                    if (! $user->canChatWith($member)) {
                        $validator->errors()->add('member_ids', 'Semua anggota harus berasal dari daftar kontak satu marga.');
                    }
                });
        }];
    }

    /** @return array<int, int> */
    public function memberIds(): array
    {
        $memberIds = $this->input('member_ids', []);

        if (! is_array($memberIds)) {
            return [];
        }

        return array_values(array_map(static fn (mixed $id): int => (int) $id, $memberIds));
    }
}

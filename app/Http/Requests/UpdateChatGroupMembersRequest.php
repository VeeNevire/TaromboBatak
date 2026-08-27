<?php

namespace App\Http\Requests;

use App\Models\ChatGroup;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateChatGroupMembersRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('chatGroup')) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'member_ids' => ['present', 'array', 'max:100'],
            'member_ids.*' => ['integer', 'distinct', Rule::exists(User::class, 'id')],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            /** @var ChatGroup $group */
            $group = $this->route('chatGroup');
            User::query()->whereKey($this->memberIds())->get()
                ->each(function (User $member) use ($group, $validator): void {
                    if ($member->marga_id !== $group->marga_id || $member->isAdmin()) {
                        $validator->errors()->add('member_ids', 'Anggota grup harus merupakan akun non-admin dengan marga yang sama.');
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

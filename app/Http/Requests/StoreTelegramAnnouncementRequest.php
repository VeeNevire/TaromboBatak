<?php

namespace App\Http\Requests;

use App\Models\ChatGroup;
use App\Models\TelegramAnnouncement;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTelegramAnnouncementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null && ! $this->user()->isAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'target_type' => ['required', Rule::in([TelegramAnnouncement::TARGET_CONTACTS, TelegramAnnouncement::TARGET_GROUP])],
            'body' => ['required', 'string', 'max:2000'],
            'contact_ids' => ['required_if:target_type,contacts', 'array', 'max:100'],
            'contact_ids.*' => ['integer', 'distinct', Rule::exists(User::class, 'id')],
            'chat_group_id' => ['required_if:target_type,group', 'nullable', Rule::exists(ChatGroup::class, 'id')],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['body' => trim((string) $this->input('body'))]);
    }

    /** @return array<int, int> */
    public function contactIds(): array
    {
        $contactIds = $this->input('contact_ids', []);

        if (! is_array($contactIds)) {
            return [];
        }

        return array_values(array_map(static fn (mixed $id): int => (int) $id, $contactIds));
    }

    public function chatGroupId(): int
    {
        return (int) $this->input('chat_group_id');
    }
}

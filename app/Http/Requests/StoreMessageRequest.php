<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreMessageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $contact = $this->route('contact');

        return $contact instanceof User
            && $this->user()?->canChatWith($contact) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'body' => ['nullable', 'string', 'max:2000', 'required_without:attachments'],
            'attachments' => ['nullable', 'array', 'max:5', 'required_without:body'],
            'attachments.*' => [
                'file',
                'max:25600',
                'mimes:jpg,jpeg,png,webp,gif,mp4,webm,mp3,m4a,wav,ogg,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,zip,rar',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $body = trim((string) $this->input('body'));

        $this->merge(['body' => $body !== '' ? $body : null]);
    }
}

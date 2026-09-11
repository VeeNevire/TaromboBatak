<?php

namespace App\Http\Requests;

use App\Models\FeedPost;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFeedPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        $feedPost = $this->route('feedPost');

        return $feedPost instanceof FeedPost
            && $this->user()?->can('update', $feedPost) === true;
    }

    /**
     * Images are fixed once a status is published; only the text and its
     * audience can be revised.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:2000'],
            'audience' => ['sometimes', 'required', 'in:public,marga'],
            'marga_ids' => ['exclude_unless:audience,marga', 'required', 'array', 'min:1'],
            'marga_ids.*' => ['required', 'integer', 'distinct', 'exists:margas,id'],
        ];
    }
}

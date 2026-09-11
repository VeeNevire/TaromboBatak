<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class StoreFeedPostRequest extends FormRequest
{
    /** Feed images are uploaded by any signed-in member, so SVG is not allowed. */
    public const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    public const MAX_IMAGES = 4;

    public const MAX_IMAGE_BYTES = 2 * 1024 * 1024;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'body' => ['required_without:images', 'nullable', 'string', 'max:2000'],
            'audience' => ['sometimes', 'required', 'in:public,marga'],
            'marga_ids' => ['exclude_unless:audience,marga', 'required', 'array', 'min:1'],
            'marga_ids.*' => ['required', 'integer', 'distinct', 'exists:margas,id'],
            'images' => ['sometimes', 'array', 'max:'.self::MAX_IMAGES],
            'images.*' => ['file', $this->imageRule()],
        ];
    }

    /**
     * Accept only real, small, raster image uploads.
     */
    protected function imageRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! $value instanceof UploadedFile || ! $value->isValid()) {
                $fail('File gambar tidak valid.');

                return;
            }

            if ($value->getSize() > self::MAX_IMAGE_BYTES) {
                $fail('Ukuran gambar maksimal 2MB.');
            }

            if (! in_array($value->getMimeType(), self::IMAGE_MIMES, true)) {
                $fail('Gambar harus berformat jpeg, png, webp, atau gif.');
            }
        };
    }
}

<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class StoreMargaRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:margas,name'],
            'description' => ['nullable', 'string'],
            'color' => ['nullable', 'string', 'max:32'],
            'image' => ['nullable', $this->imageRule()],
        ];
    }

    /**
     * Accept either an uploaded image file or a plain image URL.
     */
    protected function imageRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === null || $value === '') {
                return;
            }

            if ($value instanceof UploadedFile) {
                if (! $value->isValid()) {
                    $fail('File gambar tidak valid.');

                    return;
                }

                if ($value->getSize() > 2 * 1024 * 1024) {
                    $fail('Ukuran gambar maksimal 2MB.');
                }

                $mime = $value->getMimeType();

                if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml'], true)) {
                    $fail('Gambar harus berformat jpeg, png, webp, gif, atau svg.');
                }

                return;
            }

            if (! is_string($value) || $this->isInvalidImageString($value)) {
                $fail('URL gambar tidak valid.');
            }
        };
    }

    /**
     * A string image must be a full URL or an existing storage path.
     */
    protected function isInvalidImageString(string $value): bool
    {
        $trimmed = trim($value);

        return $trimmed !== ''
            && ! filter_var($trimmed, FILTER_VALIDATE_URL)
            && ! str_starts_with($trimmed, '/storage/')
            && ! str_starts_with($trimmed, 'margas/');
    }
}

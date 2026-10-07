<?php

namespace App\Http\Requests;

use App\Models\QrisConfiguration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateQrisConfigurationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:5120'],
            'instructions' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<int, \Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $hasCurrentImage = QrisConfiguration::query()->whereKey(1)->whereNotNull('image_path')->exists();

            if (! $hasCurrentImage && ! $this->hasFile('image')) {
                $validator->errors()->add('image', 'Unggah gambar QRIS terlebih dahulu.');
            }
        }];
    }
}

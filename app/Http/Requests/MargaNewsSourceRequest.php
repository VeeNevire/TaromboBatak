<?php

namespace App\Http\Requests;

use App\Models\MargaNewsSource;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class MargaNewsSourceRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $url = $this->input('website_url');

        if (! is_string($url)) {
            return;
        }

        $url = trim($url);

        if ($url !== '' && preg_match('/^https?:\/\//i', $url) !== 1) {
            $url = 'https://'.$url;
        }

        $this->merge(['website_url' => $url]);
    }

    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'website_url' => ['required', 'string', 'max:2048', 'url:http,https'],
            'is_active' => ['required', 'boolean'],
            'applies_to_all_topics' => ['required', 'boolean'],
            'topic_ids' => ['exclude_if:applies_to_all_topics,true', 'required', 'array', 'min:1'],
            'topic_ids.*' => ['integer', 'distinct', 'exists:marga_news_topics,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $url = $this->string('website_url')->trim()->toString();

            if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
                return;
            }

            $host = MargaNewsSource::normalizeDomain($url);

            if ($host === null
                || filter_var($host, FILTER_VALIDATE_IP)
                || ! str_contains($host, '.')
                || in_array($host, ['localhost', 'local'], true)
                || str_ends_with($host, '.localhost')
                || str_ends_with($host, '.local')) {
                $validator->errors()->add('website_url', 'Masukkan domain website publik yang valid.');

                return;
            }

            $source = $this->route('source');
            $duplicate = MargaNewsSource::query()
                ->where('domain', $host)
                ->when($source instanceof MargaNewsSource, fn ($query) => $query->where('id', '!=', $source->id))
                ->exists();

            if ($duplicate) {
                $validator->errors()->add('website_url', 'Domain website ini sudah terdaftar.');
            }
        }];
    }

    /** @return array{name: string, website_url: string, domain: string, is_active: bool, applies_to_all_topics: bool, notes: ?string, topic_ids: array<int, int>} */
    public function validatedSource(): array
    {
        $data = $this->validated();
        $data['website_url'] = trim($data['website_url']);
        $data['domain'] = MargaNewsSource::normalizeDomain($data['website_url']);
        $data['topic_ids'] = array_map('intval', $data['topic_ids'] ?? []);

        return $data;
    }
}

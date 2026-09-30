<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveTaromboCompileDraftRequest extends FormRequest
{
    /** Fonts a text layer may use; keep in sync with TEXT_FONTS in resources/js/lib/tarombo-text.ts. */
    public const TEXT_FONTS = [
        'Libre Caslon Text', 'Cinzel', 'Great Vibes', 'Inter', 'Instrument Sans',
        'Georgia', 'Times New Roman', 'Arial', 'Courier New',
    ];

    public function authorize(): bool
    {
        // The snapshot itself is authorized in the controller.
        return $this->user() !== null;
    }

    /** The state arrives as a JSON string next to the uploaded layer images. */
    protected function prepareForValidation(): void
    {
        $state = $this->input('state');

        if (is_string($state)) {
            $decoded = json_decode($state, true);
            $this->merge(['state' => is_array($decoded) ? $decoded : null]);
        }
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $box = fn (string $prefix, bool $required) => [
            $prefix => [$required ? 'required' : 'nullable', 'array'],
            "{$prefix}.x" => ["required_with:{$prefix}", 'numeric'],
            "{$prefix}.y" => ["required_with:{$prefix}", 'numeric'],
            "{$prefix}.width" => ["required_with:{$prefix}", 'numeric', 'gt:0'],
            "{$prefix}.height" => ["required_with:{$prefix}", 'numeric', 'gt:0'],
        ];

        return [
            // The saved compile being edited; empty starts a new one.
            'draft_id' => ['nullable', 'integer'],
            'state' => ['required', 'array'],
            'state.frame_id' => ['nullable', 'integer', 'exists:tarombo_frames,id'],
            'state.remove_background' => ['required', 'boolean'],
            ...$box('state.tree.crop', false),
            ...$box('state.tree.placement', false),
            'state.order' => ['required', 'array', 'max:50'],
            'state.order.*' => ['string', 'max:40'],
            'state.layers' => ['present', 'array', 'max:30'],
            'state.layers.*.id' => ['required', 'string', 'max:40'],
            'state.layers.*.name' => ['required', 'string', 'max:80'],
            'state.layers.*.kind' => ['required', 'in:ranting,background,text'],
            'state.layers.*.source' => ['required', 'string', 'regex:/^(tree|text|stored:[0-9a-f-]{36}|upload:\d{1,2})$/'],
            // A text layer is drawn in the browser from these settings.
            'state.layers.*.text' => ['required_if:state.layers.*.kind,text', 'nullable', 'array'],
            'state.layers.*.text.content' => ['nullable', 'string', 'max:500'],
            'state.layers.*.text.font' => ['required_with:state.layers.*.text', 'string', Rule::in(self::TEXT_FONTS)],
            'state.layers.*.text.size' => ['required_with:state.layers.*.text', 'numeric', 'min:1', 'max:2000'],
            'state.layers.*.text.color' => ['required_with:state.layers.*.text', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'state.layers.*.text.bold' => ['required_with:state.layers.*.text', 'boolean'],
            'state.layers.*.text.italic' => ['required_with:state.layers.*.text', 'boolean'],
            'state.layers.*.text.align' => ['required_with:state.layers.*.text', 'in:left,center,right'],
            ...$box('state.layers.*.crop', false),
            ...$box('state.layers.*.placement', true),
            'images' => ['nullable', 'array', 'max:30'],
            'images.*' => ['file', 'image', 'mimes:png,jpg,jpeg', 'max:20480'],
            // The compile composed in the browser, like a Produce result.
            'preview' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg', 'max:10240'],
        ];
    }

    /** Every "upload:n" source must come with its file; text and only text is drawn from "text". */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                foreach ((array) $this->input('state.layers', []) as $index => $layer) {
                    $source = is_array($layer) ? ($layer['source'] ?? null) : null;
                    $kind = is_array($layer) ? ($layer['kind'] ?? null) : null;

                    if (is_string($source) && str_starts_with($source, 'upload:')
                        && ! $this->hasFile('images.'.substr($source, 7))) {
                        $validator->errors()->add("state.layers.{$index}.source", 'Gambar lapisan tidak ikut terkirim.');
                    }

                    if (($source === 'text') !== ($kind === 'text')) {
                        $validator->errors()->add("state.layers.{$index}.source", 'Lapisan teks tidak valid.');
                    }
                }
            },
        ];
    }
}

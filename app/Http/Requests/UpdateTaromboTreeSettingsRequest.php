<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaromboTreeSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $color = ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'];

        return [
            'name_box_bg' => $color,
            'name_box_border' => $color,
            'initial_ring' => $color,
            'font_color' => $color,
            'font_size' => ['required', 'integer', 'min:6', 'max:16'],
            'font_family' => ['required', Rule::in(['sans', 'serif', 'mono'])],
            'font_bold' => ['required', 'boolean'],
            'branch_color' => $color,
            'branch_width' => ['required', 'numeric', 'min:0.5', 'max:4'],
            'lineage_color' => $color,
        ];
    }
}

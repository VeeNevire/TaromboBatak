<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class GenerateTaromboFrameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'snapshot_id' => ['required', 'integer', 'exists:tarombo_snapshots,id'],
            'frame_id' => ['required', 'integer', 'exists:tarombo_frames,id'],
        ];
    }
}

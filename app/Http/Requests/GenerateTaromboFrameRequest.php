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
            // Empty when the compile was made on a blank canvas.
            'snapshot_id' => ['nullable', 'integer', 'exists:tarombo_snapshots,id'],
            // An earlier result of this snapshot to replace instead of adding a new one.
            'target_snapshot_id' => ['nullable', 'integer', 'exists:tarombo_snapshots,id'],
            'frame_id' => ['required', 'integer', 'exists:tarombo_frames,id'],
            'image' => ['required', 'image', 'mimes:jpg,jpeg', 'mimetypes:image/jpeg', 'max:20480'],
        ];
    }
}

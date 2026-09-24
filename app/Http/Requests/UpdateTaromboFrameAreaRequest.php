<?php

namespace App\Http\Requests;

use App\Models\TaromboFrame;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateTaromboFrameAreaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'area_x' => ['required', 'integer', 'min:0'],
            'area_y' => ['required', 'integer', 'min:0'],
            'area_width' => ['required', 'integer', 'min:50'],
            'area_height' => ['required', 'integer', 'min:50'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var TaromboFrame $frame */
                $frame = $this->route('taromboFrame');

                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if ($this->integer('area_x') + $this->integer('area_width') > $frame->canvas_width
                    || $this->integer('area_y') + $this->integer('area_height') > $frame->canvas_height) {
                    $validator->errors()->add('area_width', 'Area konten harus berada di dalam gambar frame.');
                }
            },
        ];
    }
}

<?php

namespace App\Http\Requests\Auth;

use App\Support\IndonesiaRegions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompleteGoogleRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->session()->has('google_oauth');
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'marga_id' => ['nullable', 'exists:margas,id'],
            'province_code' => ['required', 'string', Rule::in(IndonesiaRegions::provinceCodes())],
            'regency_code' => [
                'required',
                'string',
                Rule::in(IndonesiaRegions::regencyCodesFor($this->input('province_code'))),
            ],
            'district_code' => [
                'required',
                'string',
                Rule::in(array_column(IndonesiaRegions::districtsFor($this->input('regency_code', '')), 'code')),
            ],
            'village_code' => [
                'required',
                'string',
                Rule::in(array_column(IndonesiaRegions::villagesFor($this->input('district_code', '')), 'code')),
            ],
        ];
    }
}

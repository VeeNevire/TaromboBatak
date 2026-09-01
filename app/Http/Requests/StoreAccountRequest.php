<?php

namespace App\Http\Requests;

use App\Support\IndonesiaRegions;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreAccountRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'role' => ['required', Rule::in(['admin', 'subadmin', 'contributor_main', 'contributor_member', 'user'])],
            'marga_id' => ['nullable', 'exists:margas,id'],
            'province_code' => [
                'nullable',
                'required_with:regency_code,district_code,village_code',
                'string',
                Rule::in(IndonesiaRegions::provinceCodes()),
            ],
            'regency_code' => [
                'nullable',
                'required_with:province_code,district_code,village_code',
                'string',
                Rule::in(IndonesiaRegions::regencyCodesFor($this->input('province_code'))),
            ],
            'district_code' => [
                'nullable',
                'required_with:province_code,regency_code,village_code',
                'string',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! IndonesiaRegions::districtBelongsToRegency($value, $this->input('regency_code'))) {
                        $fail('Kecamatan tidak sesuai dengan kabupaten/kota yang dipilih.');
                    }
                },
            ],
            'village_code' => [
                'nullable',
                'required_with:province_code,regency_code,district_code',
                'string',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! IndonesiaRegions::villageBelongsToDistrict($value, $this->input('district_code'))) {
                        $fail('Desa/kelurahan tidak sesuai dengan kecamatan yang dipilih.');
                    }
                },
            ],
            'managed_marga_ids' => ['required_if:role,contributor_main,contributor_member', 'array', 'min:1'],
            'managed_marga_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('margas', 'id')->whereNotNull('identity_person_id'),
            ],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }
}

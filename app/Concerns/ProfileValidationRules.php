<?php

namespace App\Concerns;

use App\Models\User;
use App\Support\IndonesiaRegions;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait ProfileValidationRules
{
    /**
     * Get the validation rules used to validate user profiles.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function profileRules(?int $userId = null): array
    {
        return [
            'name' => $this->nameRules(),
            'email' => $this->emailRules($userId),
            'marga_id' => ['nullable', 'integer', 'exists:margas,id'],
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
        ];
    }

    /**
     * Get the validation rules used to validate user names.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function nameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * Get the validation rules used to validate user emails.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function emailRules(?int $userId = null): array
    {
        return [
            'required',
            'string',
            'email',
            'max:255',
            $userId === null
                ? Rule::unique(User::class)
                : Rule::unique(User::class)->ignore($userId),
        ];
    }
}

<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Support\IndonesiaRegions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'password' => ['required', 'confirmed', Password::defaults()],
            'marga_id' => ['nullable', 'exists:margas,id'],
            'province_code' => ['required', 'string', Rule::in(IndonesiaRegions::provinceCodes())],
            'regency_code' => [
                'required',
                'string',
                Rule::in(IndonesiaRegions::regencyCodesFor($input['province_code'] ?? null)),
            ],
            'district_code' => [
                'required',
                'string',
                Rule::in(array_column(
                    IndonesiaRegions::districtsFor($input['regency_code'] ?? ''),
                    'code',
                )),
            ],
            'village_code' => [
                'required',
                'string',
                Rule::in(array_column(
                    IndonesiaRegions::villagesFor($input['district_code'] ?? ''),
                    'code',
                )),
            ],
        ])->validate();

        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
            'marga_id' => $input['marga_id'] ?? null,
            'province_code' => $input['province_code'],
            'regency_code' => $input['regency_code'],
            'district_code' => $input['district_code'],
            'village_code' => $input['village_code'],
        ]);
    }
}

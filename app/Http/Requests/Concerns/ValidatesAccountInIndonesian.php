<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Validator;

trait ValidatesAccountInIndonesian
{
    public function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'required_with' => ':attribute wajib diisi untuk melengkapi alamat.',
            'string' => ':attribute harus berupa teks.',
            'email' => 'Alamat email tidak valid.',
            'unique' => 'Alamat email sudah digunakan.',
            'max' => ['string' => ':attribute maksimal :max karakter.'],
            'min' => [
                'string' => ':attribute minimal :min karakter.',
                'array' => 'Pilih minimal :min marga yang dikelola.',
            ],
            'in' => ':attribute yang dipilih tidak valid.',
            'exists' => ':attribute yang dipilih tidak ditemukan.',
            'array' => ':attribute harus berupa daftar.',
            'integer' => ':attribute yang dipilih tidak valid.',
            'distinct' => 'Marga yang dikelola tidak boleh dipilih berulang.',
            'confirmed' => 'Konfirmasi kata sandi tidak sama dengan kata sandi.',
            'password.mixed' => 'Kata sandi harus mengandung huruf besar dan huruf kecil.',
            'password.letters' => 'Kata sandi harus mengandung setidaknya satu huruf.',
            'password.numbers' => 'Kata sandi harus mengandung setidaknya satu angka.',
            'password.symbols' => 'Kata sandi harus mengandung setidaknya satu simbol.',
            'password.uncompromised' => 'Kata sandi pernah ditemukan dalam kebocoran data. Gunakan kata sandi lain.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Nama',
            'email' => 'Email',
            'role' => 'Peran',
            'marga_id' => 'Marga',
            'province_code' => 'Provinsi',
            'regency_code' => 'Kabupaten/kota',
            'district_code' => 'Kecamatan',
            'village_code' => 'Desa/kelurahan',
            'managed_marga_ids' => 'Marga yang dikelola',
            'managed_marga_ids.*' => 'Marga yang dikelola',
            'password' => 'Kata sandi',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        // Inertia sends one message per field. Preserve every failed requirement
        // in that message instead of hiding all but the first password error.
        $validator->after(function (Validator $validator): void {
            foreach ($validator->errors()->messages() as $field => $messages) {
                $validator->errors()->forget($field);
                $validator->errors()->add($field, implode("\n", array_unique($messages)));
            }
        });
    }
}

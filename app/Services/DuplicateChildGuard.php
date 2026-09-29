<?php

namespace App\Services;

use App\Models\Person;
use Illuminate\Validation\ValidationException;

class DuplicateChildGuard
{
    public function assertCanCreate(int $fatherId, string $name, string $field): void
    {
        $normalizedName = mb_strtolower(trim($name));

        // Placeholder names do not identify a person and may occur repeatedly.
        if ($normalizedName === '' || $normalizedName === 'n/a') {
            return;
        }

        if (Person::query()
            ->where('father_id', $fatherId)
            ->whereRaw('LOWER(TRIM(name)) = ?', [$normalizedName])
            ->exists()) {
            throw ValidationException::withMessages([
                $field => 'Nama ini sudah tercatat sebagai anak dari ayah yang dipilih. Pilih anggota yang sudah ada untuk memperbaruinya.',
            ]);
        }
    }
}

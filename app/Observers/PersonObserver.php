<?php

namespace App\Observers;

use App\Models\FamilyTreeActivity;
use App\Models\Person;

class PersonObserver
{
    private const LABELS = [
        'name' => 'Nama', 'alias' => 'Alias', 'gender' => 'Jenis kelamin',
        'marga_id' => 'Marga (ID)', 'father_id' => 'Ayah (ID)', 'mother_id' => 'Ibu (ID)',
        'birth_year' => 'Tahun lahir', 'death_year' => 'Tahun wafat',
        'birth_order' => 'Urutan lahir', 'sibling_count' => 'Jumlah saudara',
        'image' => 'Foto', 'bio' => 'Biografi', 'related_stories' => 'Cerita terkait',
        'spouse' => 'Pasangan', 'spouse_marga' => 'Marga pasangan',
        'is_public' => 'Visibilitas publik', 'pending_father' => 'Status ayah',
        'province_code' => 'Provinsi', 'regency_code' => 'Kabupaten',
        'district_code' => 'Kecamatan', 'village_code' => 'Desa', 'chain' => 'Rantai silsilah',
    ];

    public function updating(Person $person): void
    {
        if (auth()->id() && array_intersect_key($person->getDirty(), self::LABELS)) {
            $person->updated_by = auth()->id();
        }
    }

    public function updated(Person $person): void
    {
        $changes = array_intersect_key($person->getChanges(), self::LABELS);
        if (! auth()->id() || $changes === []) {
            return;
        }
        $details = collect($changes)->map(function ($value, $field) use ($person) {
            $format = fn ($value) => $value === null || $value === '' ? 'kosong' : (is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (is_bool($value) ? ($value ? 'Ya' : 'Tidak') : (string) $value));

            return self::LABELS[$field].': '.$format($person->getOriginal($field)).' → '.$format($person->getAttribute($field));
        })->join('; ');
        $trees = $person->familyTrees()->get();
        foreach ($trees->isEmpty() ? [null] : $trees as $tree) {
            FamilyTreeActivity::create([
                'family_tree_id' => $tree?->id,
                'owner_id' => $tree?->user_id ?? $person->created_by,
                'actor_id' => auth()->id(),
                'tree_name' => $tree?->name ?? 'Data anggota keluarga',
                'member_name' => $person->name,
                'action' => 'updated',
                'description' => 'Mengedit '.$person->name.'. '.$details,
            ]);
        }
    }
}

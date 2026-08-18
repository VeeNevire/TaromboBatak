<?php

namespace Database\Seeders;

use App\Models\Marga;
use App\Models\Person;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class TaromboSeeder extends Seeder
{
    /**
     * Warna marga yang dikenali (konsisten dengan palet frontend).
     */
    protected const MARGA_COLORS = [
        'Batak' => '#b34b1e',
        'Tatea Bulan' => '#2a527c',
        'Isumbaon' => '#3e6b48',
        'Limbong' => '#f59e0b',
        'Sagala' => '#7c3aed',
        'Silau' => '#0e7490',
        'Lontung' => '#9a3412',
        'Borbor' => '#4f46e5',
        'Pohan' => '#0f766e',
        'Naipospos' => '#15803d',
    ];

    /**
     * Palet cadangan untuk marga lain yang belum terdaftar.
     *
     * @var array<int, string>
     */
    protected const PALETTE = [
        '#b34b1e',
        '#2a527c',
        '#3e6b48',
        '#f59e0b',
        '#7c3aed',
        '#0e7490',
        '#9a3412',
        '#4f46e5',
        '#0f766e',
        '#a16207',
        '#be185d',
        '#4338ca',
        '#15803d',
        '#c2410c',
        '#475569',
        '#6d28d9',
        '#b45309',
        '#1e40af',
        '#047857',
        '#9d174d',
        '#334155',
        '#7c2d12',
        '#1d4ed8',
        '#a21caf',
    ];

    /**
     * Seed data tarombo (marga + orang) dari tarombo-tree.json.
     */
    public function run(): void
    {
        $path = base_path('resources/js/data/tarombo-tree.json');

        if (! File::exists($path)) {
            return;
        }

        $rows = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($rows)) {
            return;
        }

        $margaIds = [];
        $margaIndex = 0;

        foreach ($rows as $row) {
            $margaName = $row['marga'] ?? null;

            if ($margaName && ! isset($margaIds[$margaName])) {
                $marga = Marga::updateOrCreate(
                    ['name' => $margaName],
                    [
                        'color' => self::MARGA_COLORS[$margaName]
                            ?? self::PALETTE[$margaIndex % count(self::PALETTE)],
                    ],
                );
                $margaIds[$margaName] = $marga->id;
                $margaIndex++;
            }
        }

        $idMap = [];

        foreach ($rows as $row) {
            $parentId = $row['parentId'] ?? null;

            $person = Person::create([
                'name' => $row['name'],
                'alias' => $row['alias'] ?? null,
                'marga_id' => isset($row['marga']) ? $margaIds[$row['marga']] : null,
                'father_id' => $parentId !== null ? $idMap[$parentId] : null,
                'birth_year' => $row['birthYear'] ?? null,
                'death_year' => $row['deathYear'] ?? null,
                'image' => $row['image'] ?? null,
                'bio' => $row['bio'] ?? null,
                'is_public' => true,
            ]);

            $idMap[$row['id']] = $person->id;
        }
    }
}

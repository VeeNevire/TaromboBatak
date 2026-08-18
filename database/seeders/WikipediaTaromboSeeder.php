<?php

namespace Database\Seeders;

use App\Models\Marga;
use App\Models\Person;
use App\Models\User;
use App\Services\ChainNumberingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Re-seed the tarombo (marga + people) from the Wikipedia-derived
 * `resources/js/data/tarombo-tree.json` (Siraja Batak main tarombo, 25 people).
 *
 * Run with:
 *     php artisan db:seed --class=WikipediaTaromboSeeder
 *
 * User accounts are left untouched; existing marga ids are reused so any
 * `users.marga_id` links stay valid. Existing people are removed first.
 */
class WikipediaTaromboSeeder extends Seeder
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
     * Seed the tarombo from tarombo-tree.json.
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

        DB::transaction(function () use ($rows) {
            $this->resetPeople();
            $margaIds = $this->upsertMargas($rows);
            $this->upsertPeople($rows, $margaIds);
            $this->assignChains();
            $this->cleanupOrphanMargas(array_values($margaIds));
        });
    }

    /**
     * Remove every existing person. FK checks are toggled so the
     * self-referencing father_id link does not block the bulk delete.
     */
    protected function resetPeople(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        Person::query()->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    }

    /**
     * Upsert the margas referenced by the seed, reusing existing ids.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, int>
     */
    protected function upsertMargas(array $rows): array
    {
        $margaIds = [];
        $index = 0;

        foreach ($rows as $row) {
            $name = $row['marga'] ?? null;

            if ($name === null || isset($margaIds[$name])) {
                continue;
            }

            $marga = Marga::updateOrCreate(
                ['name' => $name],
                ['color' => self::MARGA_COLORS[$name] ?? self::PALETTE[$index % count(self::PALETTE)]],
            );

            $margaIds[$name] = $marga->id;
            $index++;
        }

        return $margaIds;
    }

    /**
     * Insert the people, linking parents through father_id and deriving
     * birth_order/sibling_count from the row order in the JSON.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, int>  $margaIds
     */
    protected function upsertPeople(array $rows, array $margaIds): void
    {
        $idMap = [];
        $orderByParent = [];
        $siblingCount = [];

        foreach ($rows as $row) {
            $parentKey = $row['parentId'] ?? '__root__';
            $siblingCount[$parentKey] = ($siblingCount[$parentKey] ?? 0) + 1;
        }

        foreach ($rows as $row) {
            $parent = $row['parentId'] ?? null;
            $parentKey = $parent ?? '__root__';
            $order = ($orderByParent[$parentKey] ?? 0) + 1;
            $orderByParent[$parentKey] = $order;

            $person = Person::create([
                'name' => $row['name'],
                'alias' => $row['alias'] ?? null,
                'marga_id' => isset($row['marga']) ? $margaIds[$row['marga']] : null,
                'father_id' => $parent !== null ? ($idMap[$parent] ?? null) : null,
                'birth_order' => $order,
                'sibling_count' => $siblingCount[$parentKey] ?? 1,
                'birth_year' => $row['birthYear'] ?? null,
                'death_year' => $row['deathYear'] ?? null,
                'image' => $row['image'] ?? null,
                'bio' => $row['bio'] ?? null,
            ]);

            $idMap[$row['id']] = $person->id;
        }
    }

    /**
     * Assign the chain numbers for the whole patrilineal lineage.
     */
    protected function assignChains(): void
    {
        app(ChainNumberingService::class)->recomputeAll();
    }

    /**
     * Remove margas that are not part of the seed, are not referenced by any
     * user, and no longer have any people.
     *
     * @param  array<int, int>  $seedMargaIds
     */
    protected function cleanupOrphanMargas(array $seedMargaIds): void
    {
        $referencedByUser = User::query()->whereNotNull('marga_id')->pluck('marga_id')->all();

        Marga::query()
            ->whereNotIn('id', $seedMargaIds)
            ->whereNotIn('id', $referencedByUser)
            ->whereDoesntHave('people')
            ->delete();
    }
}

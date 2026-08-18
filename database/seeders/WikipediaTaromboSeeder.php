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
 * `resources/js/data/tarombo-tree.json` plus 50 connected Batak demo people
 * (75 people in total).
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

        $rows = array_merge($rows, $this->additionalPeople());

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
                'gender' => $row['gender'] ?? null,
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
     * Additional connected demo people used only by this database seeder.
     * Parent rows are listed before their children so father ids can be
     * resolved in one pass.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function additionalPeople(): array
    {
        return [
            ['id' => 'raja-sihombing', 'parentId' => 'guru-tatea-bulan', 'name' => 'Raja Sihombing', 'marga' => 'Sihombing', 'gender' => 'L', 'birthYear' => '1570'],
            ['id' => 'raja-situmorang', 'parentId' => 'guru-tatea-bulan', 'name' => 'Raja Situmorang', 'marga' => 'Situmorang', 'gender' => 'L', 'birthYear' => '1575'],
            ['id' => 'raja-siregar', 'parentId' => 'tuan-saribu-raja', 'name' => 'Raja Siregar', 'marga' => 'Siregar', 'gender' => 'L', 'birthYear' => '1600'],
            ['id' => 'raja-simanjuntak', 'parentId' => 'tuan-saribu-raja', 'name' => 'Raja Simanjuntak', 'marga' => 'Simanjuntak', 'gender' => 'L', 'birthYear' => '1605'],
            ['id' => 'raja-hutabarat', 'parentId' => 'limbong-mulana', 'name' => 'Raja Hutabarat', 'marga' => 'Hutabarat', 'gender' => 'L', 'birthYear' => '1610'],
            ['id' => 'raja-pangaribuan', 'parentId' => 'limbong-mulana', 'name' => 'Raja Pangaribuan', 'marga' => 'Pangaribuan', 'gender' => 'L', 'birthYear' => '1615'],
            ['id' => 'raja-nainggolan', 'parentId' => 'sagala-raja', 'name' => 'Raja Nainggolan', 'marga' => 'Nainggolan', 'gender' => 'L', 'birthYear' => '1620'],
            ['id' => 'raja-simbolon', 'parentId' => 'sagala-raja', 'name' => 'Raja Simbolon', 'marga' => 'Simbolon', 'gender' => 'L', 'birthYear' => '1625'],
            ['id' => 'raja-sinaga', 'parentId' => 'silau-raja', 'name' => 'Raja Sinaga', 'marga' => 'Sinaga', 'gender' => 'L', 'birthYear' => '1630'],
            ['id' => 'raja-sitorus', 'parentId' => 'silau-raja', 'name' => 'Raja Sitorus', 'marga' => 'Sitorus', 'gender' => 'L', 'birthYear' => '1635'],

            ['id' => 'bona-sihombing', 'parentId' => 'raja-sihombing', 'name' => 'Bona Sihombing', 'marga' => 'Sihombing', 'gender' => 'L', 'birthYear' => '1600'],
            ['id' => 'togar-lumbantoruan', 'parentId' => 'raja-sihombing', 'name' => 'Togar Lumbantoruan', 'marga' => 'Lumbantoruan', 'gender' => 'L', 'birthYear' => '1603'],
            ['id' => 'marudut-lumbangaol', 'parentId' => 'raja-sihombing', 'name' => 'Marudut Lumbangaol', 'marga' => 'Lumbangaol', 'gender' => 'L', 'birthYear' => '1606'],
            ['id' => 'rinto-lumbanraja', 'parentId' => 'raja-sihombing', 'name' => 'Rinto Lumbanraja', 'marga' => 'Lumbanraja', 'gender' => 'L', 'birthYear' => '1609'],
            ['id' => 'duma-situmorang', 'parentId' => 'raja-situmorang', 'name' => 'Duma Situmorang', 'marga' => 'Situmorang', 'gender' => 'P', 'birthYear' => '1605'],
            ['id' => 'jhon-siringoringo', 'parentId' => 'raja-situmorang', 'name' => 'Jhon Siringoringo', 'marga' => 'Siringoringo', 'gender' => 'L', 'birthYear' => '1608'],
            ['id' => 'ria-sihaloho', 'parentId' => 'raja-situmorang', 'name' => 'Ria Sihaloho', 'marga' => 'Sihaloho', 'gender' => 'P', 'birthYear' => '1611'],
            ['id' => 'parsaoran-simare', 'parentId' => 'raja-situmorang', 'name' => 'Parsaoran Simare', 'marga' => 'Simare', 'gender' => 'L', 'birthYear' => '1614'],
            ['id' => 'binsar-siregar', 'parentId' => 'raja-siregar', 'name' => 'Binsar Siregar', 'marga' => 'Siregar', 'gender' => 'L', 'birthYear' => '1630'],
            ['id' => 'tumpak-sormin', 'parentId' => 'raja-siregar', 'name' => 'Tumpak Sormin', 'marga' => 'Sormin', 'gender' => 'L', 'birthYear' => '1633'],
            ['id' => 'hotman-dongoran', 'parentId' => 'raja-siregar', 'name' => 'Hotman Dongoran', 'marga' => 'Dongoran', 'gender' => 'L', 'birthYear' => '1636'],
            ['id' => 'lamtiur-hasibuan', 'parentId' => 'raja-siregar', 'name' => 'Lamtiur Hasibuan', 'marga' => 'Hasibuan', 'gender' => 'P', 'birthYear' => '1639'],
            ['id' => 'martua-simanjuntak', 'parentId' => 'raja-simanjuntak', 'name' => 'Martua Simanjuntak', 'marga' => 'Simanjuntak', 'gender' => 'L', 'birthYear' => '1635'],
            ['id' => 'josua-mardongan', 'parentId' => 'raja-simanjuntak', 'name' => 'Josua Mardongan', 'marga' => 'Mardongan', 'gender' => 'L', 'birthYear' => '1638'],
            ['id' => 'debora-siahaan', 'parentId' => 'raja-simanjuntak', 'name' => 'Debora Siahaan', 'marga' => 'Siahaan', 'gender' => 'P', 'birthYear' => '1641'],
            ['id' => 'efrata-hutapea', 'parentId' => 'raja-simanjuntak', 'name' => 'Efrata Hutapea', 'marga' => 'Hutapea', 'gender' => 'L', 'birthYear' => '1644'],
            ['id' => 'daniel-hutabarat', 'parentId' => 'raja-hutabarat', 'name' => 'Daniel Hutabarat', 'marga' => 'Hutabarat', 'gender' => 'L', 'birthYear' => '1640'],
            ['id' => 'naomi-silaban', 'parentId' => 'raja-hutabarat', 'name' => 'Naomi Silaban', 'marga' => 'Silaban', 'gender' => 'P', 'birthYear' => '1643'],
            ['id' => 'petrus-panggabean', 'parentId' => 'raja-hutabarat', 'name' => 'Petrus Panggabean', 'marga' => 'Panggabean', 'gender' => 'L', 'birthYear' => '1646'],
            ['id' => 'marta-simamora', 'parentId' => 'raja-hutabarat', 'name' => 'Marta Simamora', 'marga' => 'Simamora', 'gender' => 'P', 'birthYear' => '1649'],
            ['id' => 'rudi-pangaribuan', 'parentId' => 'raja-pangaribuan', 'name' => 'Rudi Pangaribuan', 'marga' => 'Pangaribuan', 'gender' => 'L', 'birthYear' => '1645'],
            ['id' => 'saut-panjaitan', 'parentId' => 'raja-pangaribuan', 'name' => 'Saut Panjaitan', 'marga' => 'Panjaitan', 'gender' => 'L', 'birthYear' => '1648'],
            ['id' => 'ester-manurung', 'parentId' => 'raja-pangaribuan', 'name' => 'Ester Manurung', 'marga' => 'Manurung', 'gender' => 'P', 'birthYear' => '1651'],
            ['id' => 'tio-sitanggang', 'parentId' => 'raja-pangaribuan', 'name' => 'Tio Sitanggang', 'marga' => 'Sitanggang', 'gender' => 'L', 'birthYear' => '1654'],
            ['id' => 'ando-nainggolan', 'parentId' => 'raja-nainggolan', 'name' => 'Ando Nainggolan', 'marga' => 'Nainggolan', 'gender' => 'L', 'birthYear' => '1650'],
            ['id' => 'riris-sinurat', 'parentId' => 'raja-nainggolan', 'name' => 'Riris Sinurat', 'marga' => 'Sinurat', 'gender' => 'P', 'birthYear' => '1653'],
            ['id' => 'berto-samosir', 'parentId' => 'raja-nainggolan', 'name' => 'Berto Samosir', 'marga' => 'Samosir', 'gender' => 'L', 'birthYear' => '1656'],
            ['id' => 'lidia-simarmata', 'parentId' => 'raja-nainggolan', 'name' => 'Lidia Simarmata', 'marga' => 'Simarmata', 'gender' => 'P', 'birthYear' => '1659'],
            ['id' => 'ompu-simbolon', 'parentId' => 'raja-simbolon', 'name' => 'Ompu Simbolon', 'marga' => 'Simbolon', 'gender' => 'L', 'birthYear' => '1655'],
            ['id' => 'rebekka-tampubolon', 'parentId' => 'raja-simbolon', 'name' => 'Rebekka Tampubolon', 'marga' => 'Tampubolon', 'gender' => 'P', 'birthYear' => '1658'],
            ['id' => 'niko-saragih', 'parentId' => 'raja-simbolon', 'name' => 'Niko Saragih', 'marga' => 'Saragih', 'gender' => 'L', 'birthYear' => '1661'],
            ['id' => 'tiurma-purba', 'parentId' => 'raja-simbolon', 'name' => 'Tiurma Purba', 'marga' => 'Purba', 'gender' => 'P', 'birthYear' => '1664'],
            ['id' => 'andalas-sinaga', 'parentId' => 'raja-sinaga', 'name' => 'Andalas Sinaga', 'marga' => 'Sinaga', 'gender' => 'L', 'birthYear' => '1660'],
            ['id' => 'febri-manik', 'parentId' => 'raja-sinaga', 'name' => 'Febri Manik', 'marga' => 'Manik', 'gender' => 'L', 'birthYear' => '1663'],
            ['id' => 'clara-sinambela', 'parentId' => 'raja-sinaga', 'name' => 'Clara Sinambela', 'marga' => 'Sinambela', 'gender' => 'P', 'birthYear' => '1666'],
            ['id' => 'roy-tambunan', 'parentId' => 'raja-sinaga', 'name' => 'Roy Tambunan', 'marga' => 'Tambunan', 'gender' => 'L', 'birthYear' => '1669'],
            ['id' => 'binsar-sitorus', 'parentId' => 'raja-sitorus', 'name' => 'Binsar Sitorus', 'marga' => 'Sitorus', 'gender' => 'L', 'birthYear' => '1665'],
            ['id' => 'elfrida-tamba', 'parentId' => 'raja-sitorus', 'name' => 'Elfrida Tamba', 'marga' => 'Tamba', 'gender' => 'P', 'birthYear' => '1668'],
            ['id' => 'jefri-sitohang', 'parentId' => 'raja-sitorus', 'name' => 'Jefri Sitohang', 'marga' => 'Sitohang', 'gender' => 'L', 'birthYear' => '1671'],
            ['id' => 'maya-silalahi', 'parentId' => 'raja-sitorus', 'name' => 'Maya Silalahi', 'marga' => 'Silalahi', 'gender' => 'P', 'birthYear' => '1674'],
        ];
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

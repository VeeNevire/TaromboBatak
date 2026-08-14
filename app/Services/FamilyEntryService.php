<?php

namespace App\Services;

use App\Models\Marga;
use App\Models\Person;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FamilyEntryService
{
    /**
     * Persist a whole family entry (father, mother, and all sibling rows)
     * inside a single transaction.
     *
     * @param  array<string, mixed>  $data  Validated request data.
     * @param  int|null  $forcedMargaId  When set, every family member is forced
     *                                   into this marga and free-text marga
     *                                   creation is disabled.
     * @param  int|null  $createdBy  User id stamped on newly created records
     *                               so their owner can edit them later.
     * @return array{father: Person|null, mother: Person|null, children: Collection<int, Person>}
     */
    public function save(array $data, ?int $forcedMargaId = null, ?int $createdBy = null): array
    {
        $result = DB::transaction(function () use ($data, $forcedMargaId, $createdBy) {
            $fatherMargaId = $forcedMargaId
                ?? $this->resolveMargaId(
                    ($data['father'] ?? [])['marga_id'] ?? null,
                    ($data['father'] ?? [])['new_marga'] ?? null,
                )
                ?? $data['marga_id']
                ?? null;

            $father = $this->resolveParent(
                $data['father_id'] ?? null,
                $data['father'] ?? null,
                $fatherMargaId,
                $forcedMargaId,
                $createdBy,
            );

            $mother = $this->resolveParent(
                $data['mother_id'] ?? null,
                $data['mother'] ?? null,
                null,
                $forcedMargaId,
                $createdBy,
            );

            $children = $this->upsertChildren(
                $data['children'] ?? [],
                $fatherMargaId,
                $father?->id,
                $mother?->id,
                $data['sibling_count'] ?? null,
                $data['birth_order'] ?? null,
                $data,
                $forcedMargaId,
                $createdBy,
            );

            return [
                'father' => $father,
                'mother' => $mother,
                'children' => $children,
            ];
        });

        if ($result['father'] !== null) {
            app(TaromboNumberingService::class)->recomputeFromAncestor($result['father']);
        }

        $this->createLeaderGaps(
            $data,
            $forcedMargaId ?? $data['marga_id'] ?? null,
            $createdBy,
        );

        return $result;
    }

    /**
     * Resolve a parent record from a linked id or a free-text entry.
     * Reuses an existing record with the same name to avoid duplicates.
     *
     * @param  array<string, mixed>|null  $data
     */
    protected function resolveParent(?int $parentId, ?array $data, ?int $margaId, ?int $forcedMargaId = null, ?int $createdBy = null): ?Person
    {
        if ($parentId && $forcedMargaId === null) {
            $parent = Person::find($parentId);

            if ($parent) {
                $this->applyYears($parent, $data);

                return $parent;
            }
        }

        $name = $this->normalizeName($data['name'] ?? null);

        if ($name === null) {
            return null;
        }

        $flatNomor = $this->normalizeNomor($data['nomor'] ?? null);
        // Father gets margaId (non-null), mother gets null -> use this to distinguish
        $asLeader = $margaId !== null;

        $parent = Person::query()
            ->where('name', $name)
            ->when($forcedMargaId !== null, fn ($query) => $query->where('marga_id', $forcedMargaId))
            ->first();

        if ($parent === null && $flatNomor !== null) {
            // Mindah ke slot flat yang diminta (mis. ayah = nomor focus - 1).
            // Reuse record yang sudah menempati slot itu, tanpa duplikasi nomor.
            $parent = Person::query()
                ->where('nomor', $flatNomor)
                ->when($margaId !== null, fn ($query) => $query->where('marga_id', $margaId))
                ->first();
        }

        if ($parent === null) {
            $parent = Person::create(array_filter([
                'name' => $name,
                'marga_id' => $margaId,
                'is_leader' => $asLeader,
                ...($asLeader && $flatNomor !== null ? [
                    'nomor' => $flatNomor,
                    'nomor_manual' => true,
                ] : []),
                'created_by' => $createdBy,
                'birth_year' => $data['birth_year'] ?? null,
                'death_year' => $data['death_year'] ?? null,
            ], fn ($value) => $value !== null));

            if ($asLeader && $flatNomor !== null) {
                $this->linkToPreviousLeader($parent, $flatNomor, $margaId);
            }

            return $parent;
        } else {
            // Isi placeholder "N/A" dengan nama ayah yang baru diketik.
            if ($parent->isNa() && $name !== null) {
                $parent->update(['name' => $name]);
            }

            $this->applyYears($parent, $data);

            if ($margaId !== null && $parent->marga_id === null) {
                $parent->update(['marga_id' => $margaId]);
            }

            // Orang tua yang belum punya nomor otomatis menempati slot flat yang
            // diminta (ayah = nomor focus - 1) sehingga rantai nomor menyambung.
            if ($asLeader && $flatNomor !== null && $parent->nomor === null) {
                $parent->update([
                    'is_leader' => true,
                    'nomor' => $flatNomor,
                    'nomor_manual' => true,
                ]);

                $this->linkToPreviousLeader($parent, $flatNomor, $margaId);
            } elseif ($asLeader && !$parent->is_leader) {
                // Auto mode: ensure father is marked as leader even without manual nomor
                $parent->update(['is_leader' => true]);
            }
        }

        return $parent;
    }

    /**
     * Hubungkan pemimpin yang menempati slot datar ke pemimpin slot sebelumnya
     * saat ada, sehingga rantai patrilineal tetap tersambung secara fisik
     * (mis. ayah di slot 8 terhubung ke kakek di slot 7).
     */
    protected function linkToPreviousLeader(Person $person, string $flatNomor, ?int $margaId): void
    {
        $slot = (int) $flatNomor;

        if ($slot < 2 || $person->father_id !== null) {
            return;
        }

        $prev = Person::query()
            ->where('is_leader', true)
            ->where('nomor', (string) ($slot - 1))
            ->when($margaId !== null, fn ($query) => $query->where('marga_id', $margaId))
            ->first();

        if ($prev !== null && $prev->id !== $person->id) {
            $person->update(['father_id' => $prev->id]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $data
     * @return Collection<int, Person>
     */
    protected function upsertChildren(
        array $rows,
        ?int $fatherMargaId,
        ?int $fatherId,
        ?int $motherId,
        ?int $siblingCount,
        mixed $focusOrder,
        array $data,
        ?int $forcedMargaId = null,
        ?int $createdBy = null,
    ): Collection {
        $children = new Collection;

        foreach (array_values($rows) as $index => $row) {
            $isFocus = $focusOrder !== null && (int) $focusOrder === $index + 1;

            $rowNomor = $this->normalizeNomor($row['nomor'] ?? null);

            if ($rowNomor === null && $isFocus) {
                $rowNomor = $this->normalizeNomor($data['nomor'] ?? null);
            }

            $focusedFields = $isFocus ? [
                'is_leader' => true,
                'alias' => $data['alias'] ?? null,
                'birth_year' => $data['birth_year'] ?? null,
                'death_year' => $data['death_year'] ?? null,
                'image' => $data['image'] ?? null,
                'bio' => $data['bio'] ?? null,
            ] : [];

            $childMargaId = $forcedMargaId
                ?? $this->resolveMargaId(
                    $row['marga_id'] ?? null,
                    $row['new_marga'] ?? null,
                )
                ?? $fatherMargaId;

            $attributes = array_filter([
                'name' => $this->normalizeName($row['name'] ?? null) ?? 'N/A',
                'gender' => $row['gender'] ?? null,
                'spouse' => $row['spouse'] ?? null,
                'spouse_marga' => $row['spouse_marga'] ?? null,
                'marga_id' => $childMargaId,
                'father_id' => $fatherId,
                'mother_id' => $motherId,
                'birth_order' => $index + 1,
                'sibling_count' => $siblingCount,
                ...($rowNomor !== null ? ['nomor' => $rowNomor, 'nomor_manual' => true] : []),
                ...$focusedFields,
            ], fn ($value) => $value !== null);

            $child = isset($row['id']) ? Person::find($row['id']) : null;

            if ($child) {
                $child->update($attributes);
            } else {
                $child = Person::create([...$attributes, 'created_by' => $createdBy]);
            }

            $children->push($child);
        }

        return $children;
    }

    /**
     * Fill any gaps in the flat leader "nomor silsilah" sequence with N/A
     * placeholder leaders.
     *
     * When a family is saved the focus person (or staff) may specify a manual
     * leader nomor (e.g. "9") while only nomor 1-6 exist. The missing slots
     * (7, 8) are inserted as N/A leaders so they can be filled in later.
     *
     * @param  array<string, mixed>  $data
     */
    protected function createLeaderGaps(array $data, ?int $margaId, ?int $createdBy): void
    {
        $slots = [];

        $focusNomor = $this->normalizeNomor($data['nomor'] ?? null);

        if ($focusNomor !== null) {
            $parsed = (int) $focusNomor;

            if ($parsed >= 1) {
                $slots[] = $parsed;
            }
        }

        if ($slots === []) {
            return;
        }

        $maxSlot = max($slots);

        $existing = Person::query()
            ->where('is_leader', true)
            ->where('nomor_manual', true)
            ->when($margaId !== null, fn ($query) => $query->where('marga_id', $margaId))
            ->whereNotNull('nomor')
            ->pluck('nomor')
            ->map(fn (string $nomor) => (int) $nomor)
            ->filter(fn (int $nomor) => $nomor > 0)
            ->values()
            ->all();

        $taken = Person::query()
            ->whereNotNull('nomor')
            ->pluck('nomor')
            ->map(fn (string $nomor) => (int) $nomor)
            ->filter(fn (int $nomor) => $nomor > 0)
            ->values()
            ->all();

        $used = array_flip($existing);
        $takenByAnyPerson = array_flip($taken);

        for ($n = 1; $n <= $maxSlot; $n++) {
            if (isset($used[$n]) || isset($takenByAnyPerson[$n])) {
                continue;
            }

            Person::create([
                'name' => 'N/A',
                'marga_id' => $margaId,
                'is_leader' => true,
                'nomor' => (string) $n,
                'nomor_manual' => true,
                'created_by' => $createdBy,
            ]);
        }
    }

    /**
     * Resolve a marga from an existing id or a new name. When a name is
     * provided it is reused when it already exists, otherwise a new marga is
     * created on the fly.
     */
    protected function resolveMargaId(mixed $margaId, mixed $newMarga): ?int
    {
        $name = $this->normalizeName($newMarga);

        if ($name !== null) {
            return Marga::firstOrCreate(['name' => $name])->id;
        }

        if ($margaId) {
            return (int) $margaId;
        }

        return null;
    }

    /**
     * Normalize a free-text number. Returns null when empty, so an empty
     * number means "auto" and a filled one means a manual override.
     */
    protected function normalizeNomor(mixed $nomor): ?string
    {
        if (! is_string($nomor)) {
            return null;
        }

        $trimmed = trim($nomor);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    protected function applyYears(Person $person, ?array $data): void
    {
        if ($data === null) {
            return;
        }

        $updates = array_filter([
            'birth_year' => $data['birth_year'] ?? null,
            'death_year' => $data['death_year'] ?? null,
        ], fn ($value) => $value !== null);

        if ($updates !== []) {
            $person->update($updates);
        }
    }

    /**
     * Normalize a free-text name. Returns null when the field is empty or
     * explicitly marked as "N/A" (placeholder for unknown data).
     */
    protected function normalizeName(mixed $name): ?string
    {
        if (! is_string($name)) {
            return null;
        }

        $trimmed = trim($name);

        if ($trimmed === '' || mb_strtoupper($trimmed) === 'N/A') {
            return null;
        }

        return $trimmed;
    }
}

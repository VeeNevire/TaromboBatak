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
     *                                    into this marga and free-text marga
     *                                    creation is disabled.
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

        $parent = Person::query()
            ->where('name', $name)
            ->when($forcedMargaId !== null, fn ($query) => $query->where('marga_id', $forcedMargaId))
            ->first();

        if ($parent === null) {
            $parent = Person::create(array_filter([
                'name' => $name,
                'marga_id' => $margaId,
                'created_by' => $createdBy,
                'birth_year' => $data['birth_year'] ?? null,
                'death_year' => $data['death_year'] ?? null,
            ], fn ($value) => $value !== null));
        } else {
            $this->applyYears($parent, $data);

            if ($margaId !== null && $parent->marga_id === null) {
                $parent->update(['marga_id' => $margaId]);
            }
        }

        return $parent;
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

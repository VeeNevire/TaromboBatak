<?php

namespace App\Services;

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
     * @return array{father: Person|null, mother: Person|null, children: Collection<int, Person>}
     */
    public function save(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $margaId = $data['marga_id'] ?? null;

            $father = $this->resolveParent(
                $data['father_id'] ?? null,
                $data['father'] ?? null,
                $margaId,
            );

            $mother = $this->resolveParent(
                $data['mother_id'] ?? null,
                $data['mother'] ?? null,
                null,
            );

            $children = $this->upsertChildren(
                $data['children'] ?? [],
                $margaId,
                $father?->id,
                $mother?->id,
                $data['sibling_count'] ?? null,
                $data['birth_order'] ?? null,
                $data,
            );

            return [
                'father' => $father,
                'mother' => $mother,
                'children' => $children,
            ];
        });
    }

    /**
     * Resolve a parent record from a linked id or a free-text entry.
     * Reuses an existing record with the same name to avoid duplicates.
     *
     * @param  array<string, mixed>|null  $data
     */
    protected function resolveParent(?int $parentId, ?array $data, ?int $margaId): ?Person
    {
        if ($parentId) {
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
            ->first();

        if ($parent === null) {
            $parent = Person::create(array_filter([
                'name' => $name,
                'marga_id' => $margaId,
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
        ?int $margaId,
        ?int $fatherId,
        ?int $motherId,
        ?int $siblingCount,
        mixed $focusOrder,
        array $data,
    ): Collection {
        $children = new Collection();

        foreach (array_values($rows) as $index => $row) {
            $isFocus = $focusOrder !== null && (int) $focusOrder === $index + 1;

            $focusedFields = $isFocus ? [
                'nomor' => $data['nomor'] ?? null,
                'alias' => $data['alias'] ?? null,
                'birth_year' => $data['birth_year'] ?? null,
                'death_year' => $data['death_year'] ?? null,
                'image' => $data['image'] ?? null,
                'bio' => $data['bio'] ?? null,
            ] : [];

            $attributes = array_filter([
                'name' => $this->normalizeName($row['name'] ?? null) ?? 'N/A',
                'gender' => $row['gender'] ?? null,
                'spouse' => $row['spouse'] ?? null,
                'spouse_marga' => $row['spouse_marga'] ?? null,
                'marga_id' => $margaId,
                'father_id' => $fatherId,
                'mother_id' => $motherId,
                'birth_order' => $index + 1,
                'sibling_count' => $siblingCount,
                'nomor' => $row['nomor'] ?? null,
                ...$focusedFields,
            ], fn ($value) => $value !== null);

            $child = isset($row['id']) ? Person::find($row['id']) : null;

            if ($child) {
                $child->update($attributes);
            } else {
                $child = Person::create($attributes);
            }

            $children->push($child);
        }

        return $children;
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
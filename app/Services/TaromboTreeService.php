<?php

namespace App\Services;

use App\Models\Marga;
use App\Models\Person;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TaromboTreeService
{
    /**
     * Build the rows needed by the radial tarombo diagram.
     *
     * @param  Builder<Person>  $query
     * @return array<int, array<string, mixed>>
     */
    public function rows(Builder $query): array
    {
        return $query
            ->with(['marga', 'children'])
            ->get()
            ->map(fn (Person $person) => [
                'id' => (string) $person->id,
                'name' => $person->name,
                'alias' => $person->alias,
                'marga' => $person->marga->name ?? 'Batak',
                'parentId' => $person->father_id !== null ? (string) $person->father_id : null,
                'birthYear' => $person->birth_year,
                'birthOrder' => $person->birth_order,
                'chain' => $person->chain,
                'pending' => (bool) $person->pending_father,
                'gender' => $person->gender,
                'spouse' => $person->spouse,
                'image' => $person->image,
                'bio' => $person->bio,
                'childrenNames' => $person->children
                    ->sortBy('birth_year')
                    ->map(fn (Person $child) => $child->birth_year
                        ? $child->name.' ('.$child->birth_year.')'
                        : $child->name)
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Build an ancestry-preserving, bounded, public-safe tree payload.
     *
     * @return array{rows: array<int, array<string, mixed>>, truncated: bool}
     */
    public function publicRows(): array
    {
        $maxDepth = max(1, (int) config('tarombo.public_max_depth'));
        $maxNodes = max(1, (int) config('tarombo.public_max_nodes'));
        $people = new Collection;
        $truncated = false;

        $roots = Person::query()
            ->public()
            ->whereNull('father_id')
            ->with('marga')
            ->orderBy('id')
            ->limit($maxNodes + 1)
            ->get();

        if ($roots->count() > $maxNodes) {
            $truncated = true;
            $roots = $roots->take($maxNodes);
        }

        $people = $people->concat($roots);
        $frontier = $roots->pluck('id');

        for ($depth = 2; $depth <= $maxDepth && $frontier->isNotEmpty(); $depth++) {
            $remaining = $maxNodes - $people->count();

            if ($remaining <= 0) {
                $truncated = Person::query()->public()->whereIn('father_id', $frontier)->exists() || $truncated;
                break;
            }

            $children = Person::query()
                ->public()
                ->whereIn('father_id', $frontier)
                ->with('marga')
                ->orderBy('father_id')
                ->orderBy('birth_order')
                ->orderBy('id')
                ->limit($remaining + 1)
                ->get();

            if ($children->count() > $remaining) {
                $truncated = true;
                $children = $children->take($remaining);
            }

            $people = $people->concat($children);
            $frontier = $children->pluck('id');
        }

        if ($frontier->isNotEmpty() && Person::query()->public()->whereIn('father_id', $frontier)->exists()) {
            $truncated = true;
        }

        return [
            'rows' => $people
                ->map(fn (Person $person) => [
                    'id' => (string) $person->id,
                    'name' => $person->name,
                    'alias' => $person->alias,
                    'marga' => $person->marga->name ?? 'Batak',
                    'parentId' => $person->father_id !== null ? (string) $person->father_id : null,
                    'birthOrder' => $person->birth_order,
                    'chain' => $person->chain,
                    'pending' => (bool) $person->pending_father,
                ])
                ->values()
                ->all(),
            'truncated' => $truncated,
        ];
    }

    /**
     * Return only the centered person and a bounded descendant subtree.
     *
     * @return array<int, array<string, mixed>>
     */
    public function rowsForPerson(Person $person): array
    {
        $maxDepth = max(1, (int) config('tarombo.person_max_depth'));
        $maxNodes = max(1, (int) config('tarombo.person_max_nodes'));
        $ids = collect([$person->id]);
        $frontier = collect([$person->id]);

        for ($depth = 2; $depth <= $maxDepth && $frontier->isNotEmpty() && $ids->count() < $maxNodes; $depth++) {
            $children = Person::query()
                ->whereIn('father_id', $frontier)
                ->orderBy('birth_order')
                ->orderBy('id')
                ->limit($maxNodes - $ids->count())
                ->pluck('id');
            $ids = $ids->concat($children);
            $frontier = $children;
        }

        return $this->rows(Person::query()->whereIn('id', $ids)->orderBy('id'));
    }

    /**
     * Build the marga legend for the radial tarombo diagram.
     *
     * @return array<int, array{name: string, color: string}>
     */
    public function margas(?int $margaId = null, bool $publicOnly = false): array
    {
        return Marga::query()
            ->when($margaId !== null, fn (Builder $query) => $query->where('id', $margaId))
            ->when($publicOnly, fn (Builder $query) => $query->whereHas('people', fn (Builder $people) => $people->where('is_public', true)))
            ->orderBy('name')
            ->get()
            ->map(fn (Marga $marga) => [
                'name' => $marga->name,
                'color' => $marga->color ?? '#b34b1e',
            ])
            ->all();
    }
}

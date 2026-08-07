<?php

namespace App\Services;

use App\Models\Marga;
use App\Models\Person;
use Illuminate\Database\Eloquent\Builder;

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
                'nomor' => $person->nomor,
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
     * Build the marga legend for the radial tarombo diagram.
     *
     * @return array<int, array{name: string, color: string}>
     */
    public function margas(?int $margaId = null): array
    {
        return Marga::query()
            ->when($margaId !== null, fn (Builder $query) => $query->where('id', $margaId))
            ->orderBy('name')
            ->get()
            ->map(fn (Marga $marga) => [
                'name' => $marga->name,
                'color' => $marga->color ?? '#b34b1e',
            ])
            ->all();
    }
}

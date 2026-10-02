<?php

namespace App\Services;

use App\Models\Person;
use Illuminate\Support\Collection;

class MargaIdentityPersonService
{
    /** @return Collection<int, array{id: int, name: string, marga: string|null, chain: string, generation: int}> */
    public function options(): Collection
    {
        $people = Person::query()
            ->whereNotNull('chain')
            ->with('marga:id,name')
            ->orderBy('id')
            ->get(['id', 'name', 'father_id', 'marga_id', 'chain']);
        $root = $people->first(fn (Person $person) => $person->name === 'Si Raja Batak');

        if (! $root instanceof Person) {
            return collect();
        }

        $childrenByFather = $people
            ->filter(fn (Person $person) => $person->father_id !== null)
            ->groupBy('father_id');
        $connectedPeople = collect();
        $queue = [$root];
        $seen = [];

        while ($queue !== []) {
            /** @var Person $person */
            $person = array_shift($queue);

            if (isset($seen[$person->id])) {
                continue;
            }

            $seen[$person->id] = true;
            $generation = substr_count($person->chain, '-') + 1;

            $connectedPeople->push([
                'id' => $person->id,
                'name' => $person->name,
                'marga' => $person->marga?->name,
                'chain' => $person->chain,
                'generation' => $generation,
            ]);

            foreach ($childrenByFather->get($person->id, collect()) as $child) {
                $queue[] = $child;
            }
        }

        return $connectedPeople
            ->sort(fn (array $left, array $right) => strnatcmp($left['chain'], $right['chain']))
            ->values();
    }

    public function contains(int $personId): bool
    {
        return $this->options()->contains('id', $personId);
    }
}

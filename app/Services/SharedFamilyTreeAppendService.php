<?php

namespace App\Services;

use App\Models\FamilyTree;
use App\Models\FamilyTreeNode;
use App\Models\Person;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class SharedFamilyTreeAppendService
{
    public function __construct(
        private readonly FamilyTreeChainNumberingService $numbering,
    ) {}

    /**
     * Add the proposed member together with optional children and siblings.
     * The caller must run this within a transaction holding a lock on $tree.
     *
     * @param  array<string, mixed>  $payload
     */
    public function append(FamilyTree $tree, array $payload, int $createdBy): Person
    {
        $fatherNode = isset($payload['branch_father_person_id'])
            ? $this->includeBranchFather(
                $tree,
                (int) $payload['branch_father_person_id'],
            )
            : $tree->nodes()->with('person')->find($payload['father_node_id'] ?? null);
        $motherNode = isset($payload['mother_node_id'])
            ? $tree->nodes()->with('person')->find($payload['mother_node_id'])
            : null;

        if ($fatherNode === null || (isset($payload['mother_node_id']) && $motherNode === null)) {
            throw ValidationException::withMessages([
                'father_node_id' => 'Orang tua yang dipilih sudah tidak tersedia pada silsilah ini.',
            ]);
        }

        if ($motherNode !== null
            && ! $fatherNode->person->wives()->whereKey($motherNode->person_id)->exists()) {
            throw ValidationException::withMessages([
                'mother_node_id' => 'Ibu harus merupakan pasangan dari ayah yang dipilih.',
            ]);
        }

        $member = $this->appendPerson(
            tree: $tree,
            payload: $payload,
            createdBy: $createdBy,
            fatherNode: $fatherNode,
            motherNode: $motherNode,
            birthOrder: $payload['birth_order'] ?? null,
        );
        $memberNode = $tree->nodes()
            ->where('person_id', $member->id)
            ->firstOrFail();

        /** @var array<int, array<string, mixed>> $siblings */
        $siblings = $payload['siblings'] ?? [];
        foreach ($siblings as $sibling) {
            $this->appendPerson(
                tree: $tree,
                payload: $sibling,
                createdBy: $createdBy,
                fatherNode: $fatherNode,
                motherNode: $motherNode,
            );
        }

        /** @var array<int, array<string, mixed>> $children */
        $children = $payload['children'] ?? [];
        foreach ($children as $child) {
            $this->appendPerson(
                tree: $tree,
                payload: $child,
                createdBy: $createdBy,
                fatherNode: $memberNode,
                motherNode: null,
            );
        }

        $this->numbering->recompute($tree);
        $tree->touch();

        return $member;
    }

    /**
     * Confirm that a person can be used as a branch father. This does not
     * modify the tree.
     */
    public function validateBranchFather(FamilyTree $tree, Person $father): void
    {
        if ($father->gender === 'P') {
            throw ValidationException::withMessages([
                'father_person_id' => 'Ayah yang dipilih harus berjenis kelamin laki-laki.',
            ]);
        }

        $this->paternalPath($tree, $father);
    }

    /**
     * Include the selected father and his connected patrilineal path only when
     * a new member is actually submitted. A detached marga branch remains a
     * detached root in this family-tree version.
     */
    private function includeBranchFather(FamilyTree $tree, int $fatherPersonId): FamilyTreeNode
    {
        $father = Person::query()->find($fatherPersonId);

        if ($father === null) {
            throw ValidationException::withMessages([
                'father_person_id' => 'Ayah yang dipilih sudah tidak tersedia.',
            ]);
        }

        $this->validateBranchFather($tree, $father);

        $path = $this->paternalPath($tree, $father);
        $nodesByPersonId = $tree->nodes()->get()->keyBy('person_id');
        $parentNode = null;

        foreach ($path as $person) {
            $node = $nodesByPersonId->get($person->id);

            if ($node === null) {
                $tree->people()->syncWithoutDetaching([$person->id]);
                $node = FamilyTreeNode::create([
                    'family_tree_id' => $tree->id,
                    'person_id' => $person->id,
                    'father_node_id' => $parentNode?->id,
                    'birth_order' => $person->birth_order,
                ]);
                $nodesByPersonId->put($person->id, $node);
            }

            $parentNode = $node;
        }

        return $parentNode;
    }

    /** @return Collection<int, Person> */
    private function paternalPath(FamilyTree $tree, Person $father): Collection
    {
        $path = collect();
        $current = $father;
        $seen = [];

        while (true) {
            if (isset($seen[$current->id])) {
                throw ValidationException::withMessages([
                    'father_person_id' => 'Jalur ayah mengandung siklus dan tidak dapat digunakan.',
                ]);
            }

            $seen[$current->id] = true;
            $path->prepend($current);

            if ($current->id === $tree->root_person_id) {
                return $path;
            }

            if ($current->father_id === null) {
                return $path;
            }

            $current = Person::query()->find($current->father_id);

            if ($current === null) {
                throw ValidationException::withMessages([
                    'father_person_id' => 'Jalur ayah yang dipilih tidak lengkap.',
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function appendPerson(
        FamilyTree $tree,
        array $payload,
        int $createdBy,
        FamilyTreeNode $fatherNode,
        ?FamilyTreeNode $motherNode,
        ?int $birthOrder = null,
    ): Person {
        $birthOrder ??= (int) $tree->nodes()
            ->where('father_node_id', $fatherNode->id)
            ->max('birth_order') + 1;

        $memberMargaId = app(FamilyEntryService::class)->resolveMargaId(
            $payload['marga_id'] ?? null,
            $payload['new_marga'] ?? null,
        ) ?? $fatherNode->person->marga_id;

        $person = Person::create([
            'name' => $payload['name'],
            'alias' => $payload['alias'] ?? null,
            'gender' => $payload['gender'] ?? null,
            'marga_id' => $memberMargaId,
            'created_by' => $createdBy,
            'father_id' => $fatherNode->person_id,
            'mother_id' => $motherNode?->person_id,
            'birth_order' => $birthOrder,
            'birth_year' => $payload['birth_year'] ?? null,
            'death_year' => $payload['death_year'] ?? null,
            'spouse' => $payload['spouse'] ?? null,
            'spouse_marga' => $payload['spouse_marga'] ?? null,
            'bio' => $payload['bio'] ?? null,
        ]);
        $tree->people()->syncWithoutDetaching([$person->id]);
        FamilyTreeNode::create([
            'family_tree_id' => $tree->id,
            'person_id' => $person->id,
            'father_node_id' => $fatherNode->id,
            'mother_node_id' => $motherNode?->id,
            'birth_order' => $birthOrder,
        ]);

        return $person;
    }
}

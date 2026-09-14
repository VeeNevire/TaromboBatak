<?php

namespace App\Services;

use App\Models\FamilyTree;
use App\Models\FamilyTreeNode;
use App\Models\Person;
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
        $fatherNode = $tree->nodes()->with('person')->find($payload['father_node_id'] ?? null);
        $motherNode = isset($payload['mother_node_id'])
            ? $tree->nodes()->with('person')->find($payload['mother_node_id'])
            : null;

        if ($fatherNode === null || (isset($payload['mother_node_id']) && $motherNode === null)) {
            throw ValidationException::withMessages([
                'father_node_id' => 'Orang tua yang dipilih sudah tidak tersedia pada silsilah ini.',
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

        $person = Person::create([
            'name' => $payload['name'],
            'alias' => $payload['alias'] ?? null,
            'gender' => $payload['gender'] ?? null,
            'marga_id' => $fatherNode->person->marga_id,
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

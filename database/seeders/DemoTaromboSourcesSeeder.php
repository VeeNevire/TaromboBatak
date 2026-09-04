<?php

namespace Database\Seeders;

use App\Models\FamilyTree;
use App\Models\FamilyTreeNode;
use App\Models\FamilyTreeShare;
use App\Models\Marga;
use App\Models\MargaAccessRequest;
use App\Models\Person;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Create repeatable data for manually checking Tarombo source scoping.
 *
 * Run with: php artisan db:seed --class=DemoTaromboSourcesSeeder
 */
class DemoTaromboSourcesSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $accountMarga = Marga::updateOrCreate(
                ['name' => 'Silaban Demo'],
                ['color' => '#0e7490'],
            );
            $approvedMarga = Marga::updateOrCreate(
                ['name' => 'Borsak Junjungan Demo'],
                ['color' => '#b34b1e'],
            );
            $hiddenMarga = Marga::updateOrCreate(
                ['name' => 'Marga Belum Disetujui Demo'],
                ['color' => '#7c3aed'],
            );

            $viewer = User::updateOrCreate(
                ['email' => 'demo.tarombo@tarombo.test'],
                [
                    'name' => 'Demo Pemilik Tarombo',
                    'role' => 'user',
                    'marga_id' => $accountMarga->id,
                    'email_verified_at' => now(),
                    'password' => Hash::make('password'),
                ],
            );
            $sharedOwner = User::updateOrCreate(
                ['email' => 'demo.tarombo.owner@tarombo.test'],
                [
                    'name' => 'Demo Pemilik Silsilah Share',
                    'role' => 'user',
                    'marga_id' => $accountMarga->id,
                    'email_verified_at' => now(),
                    'password' => Hash::make('password'),
                ],
            );

            $ownedRoot = $this->person('Demo Ayah Silaban', $accountMarga, $viewer);
            $ownedChild = $this->person('Demo Anak Silaban', $accountMarga, $viewer, $ownedRoot);
            $ownedGrandchild = $this->person('Demo Cucu Silaban', $accountMarga, $viewer, $ownedChild);
            $ownedTree = $this->tree($viewer, 'Keluarga Demo Silaban', $ownedRoot, true);
            $this->node($ownedTree, $ownedRoot, null, '1');
            $ownedChildNode = $this->node($ownedTree, $ownedChild, null, '1-1');
            $this->node($ownedTree, $ownedGrandchild, $ownedChildNode, '1-1-1');

            $sharedRoot = $this->person('Demo Borsak dari Share', $accountMarga, $sharedOwner);
            $sharedChild = $this->person('Demo Anak dari Share', $accountMarga, $sharedOwner, $sharedRoot);
            $sharedTree = $this->tree($sharedOwner, 'Keluarga Demo yang Dibagikan', $sharedRoot);
            $this->node($sharedTree, $sharedRoot, null, '1');
            $sharedRootNode = $sharedTree->nodes()->where('person_id', $sharedRoot->id)->firstOrFail();
            $this->node($sharedTree, $sharedChild, $sharedRootNode, '1-1');
            FamilyTreeShare::updateOrCreate(
                [
                    'family_tree_id' => $sharedTree->id,
                    'recipient_id' => $viewer->id,
                ],
                [
                    'sender_id' => $sharedOwner->id,
                    'status' => FamilyTreeShare::STATUS_ACCEPTED,
                    'responded_at' => now(),
                ],
            );

            $pendingRoot = $this->person('Demo Silsilah Pending', $accountMarga, $sharedOwner);
            $pendingTree = $this->tree($sharedOwner, 'Keluarga Demo Menunggu Share', $pendingRoot);
            $this->node($pendingTree, $pendingRoot, null, '1');
            FamilyTreeShare::updateOrCreate(
                [
                    'family_tree_id' => $pendingTree->id,
                    'recipient_id' => $viewer->id,
                ],
                [
                    'sender_id' => $sharedOwner->id,
                    'status' => FamilyTreeShare::STATUS_PENDING,
                    'responded_at' => null,
                ],
            );

            $ancestor = $this->person('Demo Raja Batak', null, $sharedOwner);
            $identity = $this->person('Demo Borsak Junjungan', $approvedMarga, $sharedOwner, $ancestor);
            $approvedChild = $this->person('Demo Keturunan Borsak', $approvedMarga, $sharedOwner, $identity);
            $approvedMarga->update(['identity_person_id' => $identity->id]);
            $this->person('Demo Marga Tersembunyi', $hiddenMarga, $sharedOwner);
            MargaAccessRequest::updateOrCreate(
                [
                    'requester_id' => $viewer->id,
                    'marga_id' => $approvedMarga->id,
                ],
                [
                    'status' => MargaAccessRequest::STATUS_APPROVED,
                    'reviewed_by' => null,
                    'reviewed_at' => now(),
                    'rejection_reason' => null,
                ],
            );

            $this->command?->info('Demo sumber Tarombo siap digunakan.');
            $this->command?->line('Login: demo.tarombo@tarombo.test / password');
            $this->command?->line('Yang tampil: silsilah akun, share diterima, dan marga disetujui.');
            $this->command?->line('Share pending dan marga belum disetujui sengaja tidak tampil.');
        });
    }

    private function person(string $name, ?Marga $marga, User $creator, ?Person $father = null): Person
    {
        return Person::updateOrCreate(
            ['name' => $name],
            [
                'gender' => 'L',
                'marga_id' => $marga?->id,
                'created_by' => $creator->id,
                'father_id' => $father?->id,
                'is_public' => true,
            ],
        );
    }

    private function tree(User $owner, string $name, Person $root, bool $primary = false): FamilyTree
    {
        return FamilyTree::updateOrCreate(
            ['user_id' => $owner->id, 'name' => $name],
            [
                'root_person_id' => $root->id,
                'description' => 'Data demo untuk pengujian sumber Pohon Tarombo.',
                'is_primary' => $primary,
            ],
        );
    }

    private function node(FamilyTree $tree, Person $person, ?FamilyTreeNode $fatherNode, string $chain): FamilyTreeNode
    {
        $node = FamilyTreeNode::updateOrCreate(
            ['family_tree_id' => $tree->id, 'person_id' => $person->id],
            [
                'father_node_id' => $fatherNode?->id,
                'birth_order' => 1,
                'chain' => $chain,
                'pending_father' => false,
            ],
        );
        $tree->people()->syncWithoutDetaching([$person->id]);

        return $node;
    }
}

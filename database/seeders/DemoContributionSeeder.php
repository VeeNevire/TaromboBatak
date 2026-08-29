<?php

namespace Database\Seeders;

use App\Models\ContactRequest;
use App\Models\ContributionRequest;
use App\Models\FamilyTree;
use App\Models\FamilyTreeNode;
use App\Models\Marga;
use App\Models\Person;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Seed a small, repeatable dataset for manually testing contribution review.
 *
 * Run with: php artisan db:seed --class=DemoContributionSeeder
 */
class DemoContributionSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $marga = Marga::firstOrCreate(
                ['name' => 'Limbong'],
                ['color' => '#f59e0b'],
            );

            $contributor = User::updateOrCreate(
                ['email' => 'demo.contributor@tarombo.test'],
                [
                    'name' => 'Demo Kontributor Limbong',
                    'role' => 'contributor_member',
                    'marga_id' => $marga->id,
                    'email_verified_at' => now(),
                    'password' => Hash::make('password'),
                ],
            );

            $requester = User::updateOrCreate(
                ['email' => 'demo.user@tarombo.test'],
                [
                    'name' => 'Demo Pengaju Kontribusi',
                    'role' => 'user',
                    'marga_id' => $marga->id,
                    'email_verified_at' => now(),
                    'password' => Hash::make('password'),
                ],
            );

            $father = Person::updateOrCreate(
                ['name' => 'Demo Raja Limbong'],
                [
                    'gender' => 'L',
                    'marga_id' => $marga->id,
                    'created_by' => $contributor->id,
                    'father_id' => null,
                    'chain' => 'demo-1',
                    'birth_year' => '1920',
                    'is_public' => true,
                ],
            );

            $subject = Person::updateOrCreate(
                ['name' => 'Demo Anak Limbong'],
                [
                    'gender' => 'L',
                    'marga_id' => $marga->id,
                    'created_by' => $requester->id,
                    'father_id' => $father->id,
                    'chain' => 'demo-1-1',
                    'birth_year' => '1950',
                    'is_public' => true,
                ],
            );

            $tree = FamilyTree::updateOrCreate(
                [
                    'user_id' => $contributor->id,
                    'root_person_id' => $father->id,
                ],
                [
                    'name' => 'Silsilah Demo Limbong',
                    'description' => 'Silsilah untuk pengujian kontribusi.',
                    'is_primary' => false,
                ],
            );

            $tree->people()->syncWithoutDetaching([$father->id, $subject->id]);

            $fatherNode = FamilyTreeNode::updateOrCreate(
                ['family_tree_id' => $tree->id, 'person_id' => $father->id],
                [
                    'father_node_id' => null,
                    'birth_order' => 1,
                    'chain' => 'demo-1',
                    'pending_father' => false,
                ],
            );

            FamilyTreeNode::updateOrCreate(
                ['family_tree_id' => $tree->id, 'person_id' => $subject->id],
                [
                    'father_node_id' => $fatherNode->id,
                    'birth_order' => 1,
                    'chain' => 'demo-1-1',
                    'pending_father' => false,
                ],
            );

            ContributionRequest::updateOrCreate(
                [
                    'requester_id' => $requester->id,
                    'matched_father_id' => $father->id,
                    'subject_person_id' => $subject->id,
                ],
                [
                    'family_tree_id' => $tree->id,
                    'affected_person_ids' => [],
                    'status' => ContributionRequest::STATUS_PENDING,
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                    'rejection_reason' => null,
                ],
            );

            ContactRequest::updateOrCreate(
                [
                    'requester_id' => $contributor->id,
                    'recipient_id' => $requester->id,
                ],
                [
                    'status' => ContactRequest::STATUS_APPROVED,
                    'reviewed_at' => now(),
                ],
            );
        });

        $this->command?->info('Demo kontribusi siap digunakan.');
        $this->command?->line('Kontributor: demo.contributor@tarombo.test / password');
        $this->command?->line('Pengaju: demo.user@tarombo.test / password');
    }
}

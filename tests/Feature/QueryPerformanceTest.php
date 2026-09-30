<?php

use App\Http\Controllers\ChatGroupController;
use App\Http\Controllers\PersonController;
use App\Models\FamilyTree;
use App\Models\FamilyTreeShare;
use App\Models\Marga;
use App\Models\Person;
use App\Models\TelegramAccount;
use App\Models\User;
use App\Policies\FamilyTreePolicy;
use App\Policies\PersonPolicy;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

test('prepared person permissions preserve access without queries for each row', function () {
    $marga = Marga::factory()->create();
    $user = User::factory()->withMarga($marga->id)->create();
    $staff = User::factory()->asAdmin()->create();
    $people = Person::factory()->count(20)->create(['marga_id' => $marga->id]);
    $tree = FamilyTree::query()->create(['user_id' => $staff->id, 'root_person_id' => $people[0]->id, 'name' => 'Staff tree']);
    $tree->nodes()->create(['person_id' => $people[0]->id]);
    $tree->people()->attach($people[0]->id);
    $owned = FamilyTree::query()->create(['user_id' => $user->id, 'root_person_id' => $people[1]->id, 'name' => 'Owned tree']);
    $owned->nodes()->create(['person_id' => $people[1]->id]);
    $owned->people()->attach($people[1]->id);
    $people[2]->update(['created_by' => $user->id]);
    $policy = new PersonPolicy;
    $expected = $people->mapWithKeys(fn ($person) => [$person->id => $policy->update($user, $person)]);
    $optimized = new PersonPolicy;
    DB::enableQueryLog();
    DB::flushQueryLog();
    $optimized->prepareUpdates($user, $people);
    $preparationQueries = count(DB::getQueryLog());
    DB::disableQueryLog();

    DB::enableQueryLog();
    DB::flushQueryLog();
    $actual = $people->mapWithKeys(fn ($person) => [$person->id => $optimized->update($user, $person)]);
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($actual->all())->toBe($expected->all())
        ->and($actual[$people[0]->id])->toBeFalse()
        ->and($actual[$people[1]->id])->toBeTrue()
        ->and($queries)->toBe(0)
        ->and($preparationQueries)->toBeLessThanOrEqual(9);
});

test('authenticated people are paginated and search includes subsequent pages', function () {
    $admin = User::factory()->asAdmin()->create();
    $marga = Marga::factory()->create();
    Person::factory()->count(25)->create(['marga_id' => $marga->id, 'name' => 'Alpha']);
    $target = Person::factory()->create(['marga_id' => $marga->id, 'name' => 'Zulu target']);
    $this->actingAs($admin)->get(route('people.index'))->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->has('people.data', 12)->where('people.total', 26));
    $this->get(route('people.index', ['search' => 'Zulu']))->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->has('people.data', 1)->where('people.data.0.id', $target->id));
});

test('shared tree append permissions use loaded shares without extra queries', function () {
    $owner = User::factory()->create();
    $recipient = User::factory()->create();
    $person = Person::factory()->create();
    $tree = FamilyTree::query()->create(['user_id' => $owner->id, 'root_person_id' => $person->id, 'name' => 'Shared tree']);
    FamilyTreeShare::factory()->create(['family_tree_id' => $tree->id, 'sender_id' => $owner->id, 'recipient_id' => $recipient->id, 'status' => FamilyTreeShare::STATUS_ACCEPTED]);
    $tree->load('shares', 'user');
    $tree->setAttribute('has_approved_marga_node', false);
    $tree->setAttribute('approval_access_user_id', $recipient->id);
    DB::enableQueryLog();
    DB::flushQueryLog();
    $allowed = (new FamilyTreePolicy)->append($recipient, $tree);
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();
    expect($allowed)->toBeTrue()->and($queries)->toBe(0);
});

test('descendant previews batch roots and reuse overlapping branches', function () {
    $marga = Marga::factory()->create();
    $roots = Person::factory()->count(10)->create(['marga_id' => $marga->id]);
    foreach ($roots as $root) {
        Person::factory()->create(['father_id' => $root->id, 'marga_id' => $marga->id]);
    }
    $controller = new class extends PersonController
    {
        public function descendantPreview(array $ids): array
        {
            return $this->descendantMap($ids);
        }
    };
    DB::enableQueryLog();
    DB::flushQueryLog();
    $map = $controller->descendantPreview($roots->pluck('id')->all());
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();
    expect(array_column($map, 'count'))->toBe(array_fill(0, 10, 1))
        ->and($queries)->toBe(3);
});

test('telegram history is limited per dialog in the database', function () {
    $user = User::factory()->create();
    $account = TelegramAccount::query()->create([
        'user_id' => $user->id, 'telegram_user_id' => 123, 'private_chat_id' => 123,
        'display_name' => 'Test account', 'linked_at' => now(),
    ]);
    foreach ([101, 102] as $peer) {
        $dialog = $account->dialogs()->create(['telegram_peer_id' => $peer, 'type' => 'group', 'title' => 'Group '.$peer]);
        foreach (range(1, 35) as $number) {
            $dialog->messages()->create([
                'telegram_account_id' => $account->id, 'telegram_message_id' => $number,
                'body' => 'Message '.$number, 'sent_at' => now()->addSeconds($number),
            ]);
        }
    }
    $user->load('telegramAccount');
    DB::enableQueryLog();
    DB::flushQueryLog();
    $method = new ReflectionMethod(ChatGroupController::class, 'telegramGroupsFor');
    $result = $method->invoke(new ChatGroupController, $user);
    $sql = collect(DB::getQueryLog())->pluck('query')->implode(' ');
    DB::disableQueryLog();
    expect($result['items'])->toHaveCount(2)
        ->and($result['items'][0]['messages'])->toHaveCount(20)
        ->and($result['items'][1]['messages'])->toHaveCount(20)
        ->and($result['items'][0]['messages'][0]['body'])->toBe('Message 35')
        ->and(strtolower($sql))->toContain('row_number() over');
});

<?php

use App\Models\Marga;
use App\Models\Person;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

test('marga changes apply only to the selected scope and preserve unrelated members', function (bool $cascade) {
    $admin = User::factory()->asAdmin()->create();
    $old = Marga::factory()->create();
    $new = Marga::factory()->create();
    $root = Person::factory()->create(['marga_id' => $old->id]);
    $son = Person::factory()->create(['father_id' => $root->id, 'marga_id' => $old->id]);
    $daughter = Person::factory()->create(['father_id' => $root->id, 'marga_id' => $old->id, 'gender' => 'P']);
    $grandchild = Person::factory()->create(['father_id' => $son->id, 'marga_id' => $old->id]);
    $unrelated = Person::factory()->create(['marga_id' => $old->id]);
    $this->actingAs($admin)->patchJson(route('people.marga.update', $root), [
        'marga_id' => $new->id, 'include_descendants' => $cascade,
    ])->assertRedirect()->assertSessionHasNoErrors();
    expect($root->fresh()->marga_id)->toBe($new->id)
        ->and($root->fresh()->updated_by)->toBe($admin->id)
        ->and($unrelated->fresh()->marga_id)->toBe($old->id);
    foreach ([$son, $daughter, $grandchild] as $member) {
        expect($member->fresh()->marga_id)->toBe($cascade ? $new->id : $old->id);
    }
})->with([false, true]);

test('a denied descendant prevents all marga changes', function () {
    $admin = User::factory()->asAdmin()->create();
    $root = Person::factory()->create();
    $child = Person::factory()->create(['father_id' => $root->id]);
    $new = Marga::factory()->create();
    Gate::before(fn (User $user, string $ability, array $arguments) => $ability === 'update' && $arguments[0]->id === $child->id ? false : null);
    $this->actingAs($admin)->patchJson(route('people.marga.update', $root), [
        'marga_id' => $new->id, 'include_descendants' => true,
    ])->assertUnprocessable()->assertJsonValidationErrors('include_descendants');
    expect($root->fresh()->marga_id)->toBe($root->marga_id)
        ->and($child->fresh()->marga_id)->toBe($child->marga_id);
});

test('marga changes validate input and require authorization', function () {
    $root = Person::factory()->create();
    $admin = User::factory()->asAdmin()->create();
    $this->patchJson(route('people.marga.update', $root), [])->assertUnauthorized();
    $this->actingAs($admin)->patchJson(route('people.marga.update', $root), [
        'marga_id' => 9999999, 'include_descendants' => 'invalid',
    ])->assertUnprocessable()->assertJsonValidationErrors(['marga_id', 'include_descendants']);
    Gate::before(fn (User $user, string $ability) => $ability === 'update' ? false : null);
    $this->patchJson(route('people.marga.update', $root), [])->assertForbidden();
    $this->getJson(route('people.marga-options', $root))->assertForbidden();
});

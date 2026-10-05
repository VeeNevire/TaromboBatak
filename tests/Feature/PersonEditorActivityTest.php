<?php

use App\Models\FamilyTreeActivity;
use App\Models\Person;
use App\Models\User;

test('editing a person records the last editor and changed fields', function () {
    $editor = User::factory()->create();
    $person = Person::factory()->create(['name' => 'Nama Lama', 'birth_year' => '1980']);
    $this->actingAs($editor);
    $person->update(['name' => 'Nama Baru', 'birth_year' => '1981']);
    expect($person->fresh()->lastEditor->id)->toBe($editor->id);
    $activity = FamilyTreeActivity::query()->sole();
    expect($activity->actor_id)->toBe($editor->id)
        ->and($activity->description)->toContain('Nama: Nama Lama → Nama Baru', 'Tahun lahir: 1980 → 1981');
});

test('saving without data changes does not change the editor or add activity', function () {
    $person = Person::factory()->create();
    $this->actingAs(User::factory()->create());
    $person->save();
    expect($person->fresh()->updated_by)->toBeNull();
    $this->assertDatabaseCount('family_tree_activities', 0);
});

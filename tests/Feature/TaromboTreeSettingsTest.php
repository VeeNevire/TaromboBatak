<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function treeSettingsPayload(): array
{
    return [
        'name_box_bg' => '#ffffff',
        'name_box_border' => '#000000',
        'initial_ring' => '#cccccc',
        'font_color' => '#111111',
        'font_size' => 10,
        'font_family' => 'sans',
        'font_bold' => true,
        'branch_color' => '#ff0000',
        'branch_width' => 1.5,
        'lineage_color' => '#00ff00',
    ];
}

test('admin and sub-admin can save and reset tree display settings', function (string $state) {
    $user = User::factory()->{$state}()->create();

    $this->actingAs($user)
        ->put(route('tarombo.settings.update'), treeSettingsPayload())
        ->assertRedirect();

    expect($user->fresh()->tarombo_tree_settings)->not->toBeNull();

    $this->actingAs($user)
        ->delete(route('tarombo.settings.reset'))
        ->assertRedirect();

    expect($user->fresh()->tarombo_tree_settings)->toBeNull();
})->with(['asAdmin', 'asSubAdmin']);

test('other accounts cannot change tree display settings', function (?string $state) {
    $factory = User::factory();
    $saved = treeSettingsPayload();
    $user = ($state ? $factory->{$state}() : $factory)->create(['tarombo_tree_settings' => $saved]);

    // Authorization denials redirect back with an error toast.
    $this->actingAs($user)
        ->put(route('tarombo.settings.update'), [...$saved, 'branch_color' => '#123456'])
        ->assertRedirect();

    $this->actingAs($user)
        ->delete(route('tarombo.settings.reset'))
        ->assertRedirect();

    expect($user->fresh()->tarombo_tree_settings['branch_color'])->toBe($saved['branch_color']);
})->with([null, 'asMainContributor', 'asContributorMember']);

test('saved tree display settings are only sent to admin and sub-admin', function (?string $state, bool $receivesSettings) {
    $factory = User::factory();
    $user = ($state ? $factory->{$state}() : $factory)->create([
        'tarombo_tree_settings' => treeSettingsPayload(),
    ]);

    $this->actingAs($user)
        ->get(route('tarombo.fullscreen', ['view' => 'tree']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $receivesSettings
            ? $page->whereNot('treeSettings', null)
            : $page->where('treeSettings', null));
})->with([
    'admin' => ['asAdmin', true],
    'sub-admin' => ['asSubAdmin', true],
    'user' => [null, false],
    'contributor' => ['asMainContributor', false],
]);

test('staff can save separate initial colors thickness and shapes for each gender', function () {
    $user = User::factory()->asAdmin()->create();
    $settings = [...treeSettingsPayload(),
        'male_initial_ring' => '#123456', 'male_initial_width' => 3, 'male_initial_radius' => 0,
        'female_initial_ring' => '#abcdef', 'female_initial_width' => 1.5, 'female_initial_radius' => 25,
    ];
    $this->actingAs($user)->put(route('tarombo.settings.update'), $settings)->assertSessionHasNoErrors()->assertRedirect();
    expect($user->fresh()->tarombo_tree_settings)->toEqual($settings);
});

test('initial shape and thickness settings reject invalid values', function () {
    $user = User::factory()->asAdmin()->create();
    $this->actingAs($user)->put(route('tarombo.settings.update'), [...treeSettingsPayload(),
        'male_initial_ring' => 'red', 'male_initial_width' => -1, 'male_initial_radius' => 51,
        'female_initial_ring' => 'invalid', 'female_initial_width' => 7, 'female_initial_radius' => -1,
    ])->assertSessionHasErrors(['male_initial_ring', 'male_initial_width', 'male_initial_radius', 'female_initial_ring', 'female_initial_width', 'female_initial_radius']);
    expect($user->fresh()->tarombo_tree_settings)->toBeNull();
});

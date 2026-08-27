<?php

use App\Models\Person;
use App\Models\TaromboSnapshot;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('an authenticated account can privately save a tarombo jpg', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $person = Person::factory()->create();
    $returnUrl = route('tarombo.fullscreen', ['view' => 'tree']);

    $this->actingAs($user)
        ->from($returnUrl)
        ->post(route('tarombo.snapshots.store'), [
            'image' => UploadedFile::fake()->image('pohon.jpg', 1200, 800),
            'view' => 'tree',
            'center_person_id' => $person->id,
        ])
        ->assertRedirect($returnUrl);

    $snapshot = TaromboSnapshot::query()->sole();

    expect($snapshot->user_id)->toBe($user->id)
        ->and($snapshot->center_person_id)->toBe($person->id)
        ->and($snapshot->view)->toBe('tree')
        ->and($snapshot->path)->toStartWith("tarombo-snapshots/{$user->id}/");
    Storage::disk('local')->assertExists($snapshot->path);
});

test('a tarombo snapshot only accepts jpeg images and known views', function () {
    Storage::fake('local');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('tarombo.snapshots.store'), [
            'image' => UploadedFile::fake()->image('pohon.png'),
            'view' => 'unknown',
        ])
        ->assertSessionHasErrors(['image', 'view']);

    expect(TaromboSnapshot::query()->exists())->toBeFalse();
});

test('a guest cannot save a tarombo snapshot', function () {
    $this->post(route('tarombo.snapshots.store'))
        ->assertRedirect(route('login'));
});

test('the gallery only lists snapshots owned by the signed in account', function () {
    $user = User::factory()->create(['name' => 'Pemilik Galeri']);
    $ownSnapshot = TaromboSnapshot::factory()->for($user)->create();
    TaromboSnapshot::factory()->create();

    $this->actingAs($user)
        ->get(route('tarombo.snapshots.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tarombo/snapshots')
            ->where('accountName', 'Pemilik Galeri')
            ->has('snapshots.data', 1)
            ->where('snapshots.data.0.id', $ownSnapshot->id)
            ->where('snapshots.data.0.image_url', route('tarombo.snapshots.image', $ownSnapshot)));
});

test('only the owner can view a private snapshot image inline', function () {
    Storage::fake('local');

    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $file = UploadedFile::fake()->image('pohon.jpg');
    $path = $file->store('tarombo-snapshots/'.$owner->id, 'local');
    $snapshot = TaromboSnapshot::factory()->for($owner)->create([
        'path' => $path,
    ]);

    $this->actingAs($owner)
        ->get(route('tarombo.snapshots.image', $snapshot))
        ->assertOk()
        ->assertHeader('Cache-Control', 'max-age=0, no-store, private')
        ->assertHeader('Content-Disposition', 'inline; filename=pohon-tarombo.jpg');

    $this->actingAs($otherUser)
        ->getJson(route('tarombo.snapshots.image', $snapshot))
        ->assertForbidden();
});

test('the owner can remove a snapshot and its private file', function () {
    Storage::fake('local');

    $owner = User::factory()->create();
    $path = UploadedFile::fake()
        ->image('pohon.jpg')
        ->store('tarombo-snapshots/'.$owner->id, 'local');
    $snapshot = TaromboSnapshot::factory()->for($owner)->create([
        'path' => $path,
    ]);

    $this->actingAs($owner)
        ->delete(route('tarombo.snapshots.destroy', $snapshot))
        ->assertRedirect();

    $this->assertModelMissing($snapshot);
    Storage::disk('local')->assertMissing($path);
});

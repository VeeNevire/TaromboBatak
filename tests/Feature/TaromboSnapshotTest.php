<?php

use App\Models\Person;
use App\Models\TaromboCompileDraft;
use App\Models\TaromboFrame;
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
            ->missing('aiPrompt')
            ->has('snapshots.data', 1)
            ->where('snapshots.data.0.id', $ownSnapshot->id)
            ->where('snapshots.data.0.image_url', route('tarombo.snapshots.image', $ownSnapshot)));
});

test('the free original filter hides originals with a saved compile and compile results', function () {
    $user = User::factory()->create();
    $free = TaromboSnapshot::factory()->for($user)->create(['tarombo_frame_id' => null]);
    $used = TaromboSnapshot::factory()->for($user)->create(['tarombo_frame_id' => null]);
    TaromboCompileDraft::query()->create([
        'user_id' => $user->id,
        'tarombo_snapshot_id' => $used->id,
        'state' => [],
    ]);
    $frame = TaromboFrame::factory()->create();
    TaromboSnapshot::factory()->for($user)->create([
        'tarombo_frame_id' => $frame->id,
        'source_snapshot_id' => $free->id,
    ]);

    $this->actingAs($user)
        ->get(route('tarombo.snapshots.index', ['filter' => 'free']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filter', 'free')
            ->has('snapshots.data', 1)
            ->where('snapshots.data.0.id', $free->id));
});

test('the compile page offers only the own free originals for ranting layers', function () {
    $user = User::factory()->create();
    $current = TaromboSnapshot::factory()->for($user)->create(['tarombo_frame_id' => null]);
    $free = TaromboSnapshot::factory()->for($user)->create(['tarombo_frame_id' => null]);
    $used = TaromboSnapshot::factory()->for($user)->create(['tarombo_frame_id' => null]);
    TaromboCompileDraft::query()->create([
        'user_id' => $user->id,
        'tarombo_snapshot_id' => $used->id,
        'state' => [],
    ]);
    TaromboSnapshot::factory()->create(['tarombo_frame_id' => null]);

    $this->actingAs($user)
        ->get(route('tarombo.snapshots.compile', $current))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('freeOriginals', 1)
            ->where('freeOriginals.0.id', $free->id));
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

test('a staff snapshot stores its title, resolution, paper size, orientation and included people', function (string $state) {
    Storage::fake('local');

    $user = User::factory()->{$state}()->create();
    $person = Person::factory()->create();
    $child = Person::factory()->create(['father_id' => $person->id]);

    $this->actingAs($user)
        ->post(route('tarombo.snapshots.store'), [
            'image' => UploadedFile::fake()->image('pohon.jpg', 1200, 800),
            'view' => 'tree',
            'center_person_id' => $person->id,
            'title' => 'Pohon Raja',
            'resolution' => 17280,
            'paper_size' => 'A3',
            'orientation' => 'landscape',
            'included_person_ids' => [$child->id],
        ])
        ->assertRedirect();

    $snapshot = TaromboSnapshot::query()->sole();

    expect($snapshot->title)->toBe('Pohon Raja')
        ->and($snapshot->resolution)->toBe(17280)
        ->and($snapshot->paper_size)->toBe('A3')
        ->and($snapshot->orientation)->toBe('landscape')
        ->and($snapshot->included_person_ids)->toBe([$child->id]);
})->with(['asAdmin', 'asSubAdmin']);

test('other accounts save the tree as shown without the save dialog settings', function (?string $state) {
    Storage::fake('local');

    $factory = User::factory();
    $user = ($state ? $factory->{$state}() : $factory)->create();
    $person = Person::factory()->create();

    $this->actingAs($user)
        ->post(route('tarombo.snapshots.store'), [
            'image' => UploadedFile::fake()->image('pohon.jpg', 1200, 800),
            'view' => 'tree',
            'center_person_id' => $person->id,
            'title' => 'Pohon Raja',
            'resolution' => 8640,
            'paper_size' => 'A3',
            'orientation' => 'landscape',
            'included_person_ids' => [$person->id],
        ])
        ->assertRedirect();

    $snapshot = TaromboSnapshot::query()->sole();

    expect($snapshot->center_person_id)->toBe($person->id)
        ->and($snapshot->title)->toBeNull()
        ->and($snapshot->resolution)->toBeNull()
        ->and($snapshot->paper_size)->toBeNull()
        ->and($snapshot->orientation)->toBeNull()
        ->and($snapshot->included_person_ids)->toBeNull();
})->with([null, 'asMainContributor', 'asContributorMember']);

test('a snapshot rejects an unsupported resolution, paper size or orientation', function () {
    Storage::fake('local');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('tarombo.snapshots.store'), [
            'image' => UploadedFile::fake()->image('pohon.jpg'),
            'view' => 'tree',
            'resolution' => 9999,
            'paper_size' => 'B5',
            'orientation' => 'diagonal',
        ])
        ->assertSessionHasErrors(['resolution', 'paper_size', 'orientation']);

    expect(TaromboSnapshot::query()->exists())->toBeFalse();
});

test('staff can view a snapshot image owned by another account', function () {
    Storage::fake('local');

    $owner = User::factory()->create();
    $subAdmin = User::factory()->create(['role' => 'subadmin']);
    $path = UploadedFile::fake()
        ->image('pohon.jpg')
        ->store('tarombo-snapshots/'.$owner->id, 'local');
    $snapshot = TaromboSnapshot::factory()->for($owner)->create(['path' => $path]);

    $this->actingAs($subAdmin)
        ->get(route('tarombo.snapshots.image', $snapshot))
        ->assertOk();
});

test('the staff gallery lists snapshots from every account', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $owner = User::factory()->create(['name' => 'Pemilik Galeri']);
    $snapshot = TaromboSnapshot::factory()->for($owner)->create();

    $this->actingAs($admin)
        ->get(route('tarombo.snapshots.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tarombo/snapshots')
            ->where('canDownload', true)
            ->has('snapshots.data', 1)
            ->where('snapshots.data.0.id', $snapshot->id)
            ->where('snapshots.data.0.owner_name', 'Pemilik Galeri')
            ->where(
                'snapshots.data.0.download_url',
                route('tarombo.snapshots.download', $snapshot),
            ));
});

test('staff can download a snapshot and the download is logged', function () {
    Storage::fake('local');

    $owner = User::factory()->create();
    $admin = User::factory()->create(['role' => 'admin']);
    $path = UploadedFile::fake()
        ->image('pohon.jpg')
        ->store('tarombo-snapshots/'.$owner->id, 'local');
    $snapshot = TaromboSnapshot::factory()->for($owner)->create([
        'path' => $path,
        'title' => 'Pohon Raja',
    ]);

    $this->actingAs($admin)
        ->get(route('tarombo.snapshots.download', $snapshot))
        ->assertOk()
        ->assertDownload();

    $this->assertDatabaseHas('family_tree_activities', [
        'actor_id' => $admin->id,
        'owner_id' => $owner->id,
        'action' => 'downloaded',
        'tree_name' => 'Pohon Raja',
    ]);
});

test('a non-staff owner cannot download a snapshot', function () {
    Storage::fake('local');

    $owner = User::factory()->create();
    $path = UploadedFile::fake()
        ->image('pohon.jpg')
        ->store('tarombo-snapshots/'.$owner->id, 'local');
    $snapshot = TaromboSnapshot::factory()->for($owner)->create(['path' => $path]);

    $this->actingAs($owner)
        ->getJson(route('tarombo.snapshots.download', $snapshot))
        ->assertForbidden();
});

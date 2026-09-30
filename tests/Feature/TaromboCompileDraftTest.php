<?php

use App\Models\TaromboCompileDraft;
use App\Models\TaromboFrame;
use App\Models\TaromboSnapshot;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function compileState(array $layers = [], array $overrides = []): string
{
    return json_encode([
        'frame_id' => null,
        'remove_background' => true,
        'tree' => ['crop' => null, 'placement' => ['x' => 10, 'y' => 20, 'width' => 300, 'height' => 400]],
        'order' => ['tree', ...array_column($layers, 'id')],
        'layers' => $layers,
        ...$overrides,
    ]);
}

function compileLayer(string $id, string $source, string $kind = 'ranting'): array
{
    return [
        'id' => $id,
        'name' => 'Ranting 1',
        'kind' => $kind,
        'source' => $source,
        'crop' => null,
        'placement' => ['x' => 0, 'y' => 0, 'width' => 100, 'height' => 80],
    ];
}

beforeEach(function () {
    Storage::fake('local');
});

test('the owner saves a compile with its layer images and gets it back on the compile page', function () {
    $user = User::factory()->create();
    $snapshot = TaromboSnapshot::factory()->for($user)->create();
    $frame = TaromboFrame::factory()->create();

    $this->actingAs($user)
        ->put(route('tarombo.snapshots.compile.draft.update', $snapshot), [
            'state' => compileState(
                [compileLayer('layer-1', 'upload:0'), compileLayer('layer-2', 'tree')],
                ['frame_id' => $frame->id],
            ),
            'images' => [UploadedFile::fake()->image('ranting.png', 50, 40)],
        ])
        ->assertRedirect();

    $draft = TaromboCompileDraft::query()->sole();
    $uuid = substr($draft->state['layers'][0]['source'], 7);

    expect($draft->state['layers'][0]['source'])->toStartWith('stored:')
        ->and($draft->state['layers'][1]['source'])->toBe('tree')
        ->and($draft->tarombo_frame_id)->toBe($frame->id);
    Storage::disk('local')->assertExists(TaromboCompileDraft::imagePath($user->id, $snapshot->id, $uuid));

    $this->actingAs($user)
        ->get(route('tarombo.snapshots.compile', $snapshot))
        ->assertInertia(fn (Assert $page) => $page
            ->where('draft.state.layers.0.source', "stored:{$uuid}")
            ->where("draft.image_urls.{$uuid}", route('tarombo.snapshots.compile.draft.image', [$snapshot, $uuid])));

    $this->actingAs($user)
        ->get(route('tarombo.snapshots.compile.draft.image', [$snapshot, $uuid]))
        ->assertOk();
});

test('saving again replaces the compile and removes images no longer used', function () {
    $user = User::factory()->create();
    $snapshot = TaromboSnapshot::factory()->for($user)->create();

    $this->actingAs($user)->put(route('tarombo.snapshots.compile.draft.update', $snapshot), [
        'state' => compileState([compileLayer('layer-1', 'upload:0')]),
        'images' => [UploadedFile::fake()->image('a.png')],
    ])->assertRedirect();

    $oldUuid = substr(TaromboCompileDraft::query()->sole()->state['layers'][0]['source'], 7);

    $this->actingAs($user)->put(route('tarombo.snapshots.compile.draft.update', $snapshot), [
        'state' => compileState([compileLayer('layer-2', 'upload:0', 'background')]),
        'images' => [UploadedFile::fake()->image('b.png')],
    ])->assertRedirect();

    expect(TaromboCompileDraft::query()->count())->toBe(1);
    Storage::disk('local')->assertMissing(TaromboCompileDraft::imagePath($user->id, $snapshot->id, $oldUuid));
    expect(Storage::disk('local')->files(TaromboCompileDraft::directory($user->id, $snapshot->id)))->toHaveCount(1);
});

test('a saved compile is invalid without its uploaded images or with an unknown layer kind', function () {
    $user = User::factory()->create();
    $snapshot = TaromboSnapshot::factory()->for($user)->create();

    $this->actingAs($user)->put(route('tarombo.snapshots.compile.draft.update', $snapshot), [
        'state' => compileState([compileLayer('layer-1', 'upload:0')]),
    ])->assertSessionHasErrors('state.layers.0.source');

    $this->actingAs($user)->put(route('tarombo.snapshots.compile.draft.update', $snapshot), [
        'state' => compileState([compileLayer('layer-1', 'tree', 'pohon')]),
    ])->assertSessionHasErrors('state.layers.0.kind');

    expect(TaromboCompileDraft::query()->exists())->toBeFalse();
});

test('another account cannot save a compile for a snapshot it cannot see', function () {
    $snapshot = TaromboSnapshot::factory()->create();

    // Authorization denials redirect back with an error toast.
    $this->actingAs(User::factory()->create())
        ->put(route('tarombo.snapshots.compile.draft.update', $snapshot), [
            'state' => compileState(),
        ])
        ->assertRedirect();

    expect(TaromboCompileDraft::query()->exists())->toBeFalse();
});

test('a saved compile keeps its composed preview and lists it under Hasil Simpan', function () {
    $user = User::factory()->create();
    $snapshot = TaromboSnapshot::factory()->for($user)->create();
    $frame = TaromboFrame::factory()->create();
    $previewPath = TaromboCompileDraft::previewPath($user->id, $snapshot->id);

    $this->actingAs($user)->put(route('tarombo.snapshots.compile.draft.update', $snapshot), [
        'state' => compileState([compileLayer('layer-1', 'upload:0')], ['frame_id' => $frame->id]),
        'images' => [UploadedFile::fake()->image('a.png')],
        'preview' => UploadedFile::fake()->image('preview.jpg', 800, 600),
    ])->assertRedirect();

    Storage::disk('local')->assertExists($previewPath);

    // Saving again without a new preview must not treat it as an unused layer image.
    $uuid = substr(TaromboCompileDraft::query()->sole()->state['layers'][0]['source'], 7);

    $this->actingAs($user)->put(route('tarombo.snapshots.compile.draft.update', $snapshot), [
        'state' => compileState([compileLayer('layer-1', "stored:{$uuid}")], ['frame_id' => $frame->id]),
    ])->assertRedirect();

    Storage::disk('local')->assertExists($previewPath);

    // Another account's compile is not listed.
    TaromboCompileDraft::query()->create([
        'user_id' => User::factory()->create()->id,
        'tarombo_snapshot_id' => TaromboSnapshot::factory()->create()->id,
        'state' => json_decode(compileState(), true),
    ]);

    $this->actingAs($user)
        ->get(route('tarombo.snapshots.index', ['filter' => 'saved']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filter', 'saved')
            ->has('snapshots.data', 1)
            ->where('snapshots.data.0.id', $snapshot->id)
            ->where('snapshots.data.0.saved_compile', true)
            ->where('snapshots.data.0.has_preview', true)
            ->where('snapshots.data.0.image_url', fn (string $url) => str_starts_with(
                $url,
                route('tarombo.snapshots.compile.draft.preview', $snapshot),
            )));

    $this->actingAs($user)
        ->get(route('tarombo.snapshots.compile.draft.preview', $snapshot))
        ->assertOk();
});

test('a compile saved without a frame has no preview and falls back to the tree image', function () {
    $user = User::factory()->create();
    $snapshot = TaromboSnapshot::factory()->for($user)->create();

    $this->actingAs($user)->put(route('tarombo.snapshots.compile.draft.update', $snapshot), [
        'state' => compileState(),
        'preview' => UploadedFile::fake()->image('preview.jpg'),
    ])->assertRedirect();

    Storage::disk('local')->assertMissing(TaromboCompileDraft::previewPath($user->id, $snapshot->id));

    $this->actingAs($user)
        ->get(route('tarombo.snapshots.index', ['filter' => 'saved']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('snapshots.data.0.has_preview', false)
            ->where('snapshots.data.0.image_url', route('tarombo.snapshots.image', $snapshot)));

    $this->actingAs($user)
        ->get(route('tarombo.snapshots.compile.draft.preview', $snapshot))
        ->assertNotFound();
});

test('deleting the snapshot removes its saved compile and images', function () {
    $user = User::factory()->create();
    $snapshot = TaromboSnapshot::factory()->for($user)->create([
        'path' => UploadedFile::fake()->image('pohon.jpg')->store('tarombo-snapshots/'.$user->id, 'local'),
    ]);

    $this->actingAs($user)->put(route('tarombo.snapshots.compile.draft.update', $snapshot), [
        'state' => compileState([compileLayer('layer-1', 'upload:0')]),
        'images' => [UploadedFile::fake()->image('a.png')],
    ])->assertRedirect();

    $this->actingAs($user)->delete(route('tarombo.snapshots.destroy', $snapshot))->assertRedirect();

    expect(TaromboCompileDraft::query()->exists())->toBeFalse()
        ->and(Storage::disk('local')->files(TaromboCompileDraft::directory($user->id, $snapshot->id)))->toBe([]);
});

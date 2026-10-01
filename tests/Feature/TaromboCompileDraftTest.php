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

test('saving again with its id replaces the compile and removes images no longer used', function () {
    $user = User::factory()->create();
    $snapshot = TaromboSnapshot::factory()->for($user)->create();

    $this->actingAs($user)->put(route('tarombo.snapshots.compile.draft.update', $snapshot), [
        'state' => compileState([compileLayer('layer-1', 'upload:0')]),
        'images' => [UploadedFile::fake()->image('a.png')],
    ])->assertRedirect();

    $draft = TaromboCompileDraft::query()->sole();
    $oldUuid = substr($draft->state['layers'][0]['source'], 7);

    $this->actingAs($user)->put(route('tarombo.snapshots.compile.draft.update', $snapshot), [
        'draft_id' => $draft->id,
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

    $this->actingAs($user)->put(route('tarombo.snapshots.compile.draft.update', $snapshot), [
        'state' => compileState([compileLayer('layer-1', 'upload:0')], ['frame_id' => $frame->id]),
        'images' => [UploadedFile::fake()->image('a.png')],
        'preview' => UploadedFile::fake()->image('preview.jpg', 800, 600),
    ])->assertRedirect();

    $draft = TaromboCompileDraft::query()->sole();

    Storage::disk('local')->assertExists($draft->previewPath());

    // Saving again without a new preview must not treat it as an unused layer image.
    $uuid = substr($draft->state['layers'][0]['source'], 7);

    $this->actingAs($user)->put(route('tarombo.snapshots.compile.draft.update', $snapshot), [
        'draft_id' => $draft->id,
        'state' => compileState([compileLayer('layer-1', "stored:{$uuid}")], ['frame_id' => $frame->id]),
    ])->assertRedirect();

    Storage::disk('local')->assertExists($draft->previewPath());

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
            ->where('snapshots.data.0.draft_id', $draft->id)
            ->where('snapshots.data.0.saved_compile', true)
            ->where('snapshots.data.0.has_preview', true)
            ->where('snapshots.data.0.image_url', fn (string $url) => str_starts_with(
                $url,
                route('tarombo.compile-drafts.preview', $draft),
            )));

    $this->actingAs($user)
        ->get(route('tarombo.compile-drafts.preview', $draft))
        ->assertOk();
});

test('a compile saved without a frame has no preview and falls back to the tree image', function () {
    $user = User::factory()->create();
    $snapshot = TaromboSnapshot::factory()->for($user)->create();

    $this->actingAs($user)->put(route('tarombo.snapshots.compile.draft.update', $snapshot), [
        'state' => compileState(),
        'preview' => UploadedFile::fake()->image('preview.jpg'),
    ])->assertRedirect();

    $draft = TaromboCompileDraft::query()->sole();

    Storage::disk('local')->assertMissing($draft->previewPath());

    $this->actingAs($user)
        ->get(route('tarombo.snapshots.index', ['filter' => 'saved']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('snapshots.data.0.has_preview', false)
            ->where('snapshots.data.0.image_url', route('tarombo.snapshots.image', $snapshot)));

    $this->actingAs($user)
        ->get(route('tarombo.compile-drafts.preview', $draft))
        ->assertNotFound();
});

test('a saved compile can be duplicated under a new name and edited on its own', function () {
    $user = User::factory()->create();
    $snapshot = TaromboSnapshot::factory()->for($user)->create();
    $frame = TaromboFrame::factory()->create();

    $this->actingAs($user)->put(route('tarombo.snapshots.compile.draft.update', $snapshot), [
        'state' => compileState([compileLayer('layer-1', 'upload:0')], ['frame_id' => $frame->id]),
        'images' => [UploadedFile::fake()->image('a.png')],
        'preview' => UploadedFile::fake()->image('preview.jpg'),
    ])->assertRedirect();

    $original = TaromboCompileDraft::query()->sole();
    $uuid = substr($original->state['layers'][0]['source'], 7);

    $this->actingAs($user)
        ->post(route('tarombo.compile-drafts.duplicate', $original), ['name' => 'Versi Keluarga Besar'])
        ->assertRedirect();

    $copy = TaromboCompileDraft::query()->whereKeyNot($original->id)->sole();

    expect($copy->name)->toBe('Versi Keluarga Besar')
        ->and($copy->state)->toBe($original->state);
    Storage::disk('local')->assertExists($copy->previewPath());

    // The copy opens on the compile page by its id.
    $this->actingAs($user)
        ->get(route('tarombo.snapshots.compile', ['taromboSnapshot' => $snapshot, 'draft' => $copy->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('draft.id', $copy->id)
            ->where('draft.name', 'Versi Keluarga Besar'));

    // Changing the copy drops its layer, but the shared image stays for the original.
    $this->actingAs($user)->put(route('tarombo.snapshots.compile.draft.update', $snapshot), [
        'draft_id' => $copy->id,
        'state' => compileState([], ['frame_id' => $frame->id]),
    ])->assertRedirect();

    Storage::disk('local')->assertExists(TaromboCompileDraft::imagePath($user->id, $snapshot->id, $uuid));

    // Deleting the original frees the image nobody uses any more.
    $this->actingAs($user)
        ->delete(route('tarombo.compile-drafts.destroy', $original))
        ->assertRedirect();

    Storage::disk('local')->assertMissing(TaromboCompileDraft::imagePath($user->id, $snapshot->id, $uuid));
    Storage::disk('local')->assertMissing($original->previewPath());
    expect(TaromboCompileDraft::query()->pluck('id')->all())->toBe([$copy->id]);
});

test('a saved compile can be renamed only by its own account', function () {
    $user = User::factory()->create();
    $draft = TaromboCompileDraft::query()->create([
        'user_id' => $user->id,
        'tarombo_snapshot_id' => TaromboSnapshot::factory()->for($user)->create()->id,
        'state' => json_decode(compileState(), true),
    ]);

    $this->actingAs($user)
        ->patch(route('tarombo.compile-drafts.rename', $draft), ['name' => '  Tarombo Utama  '])
        ->assertRedirect();

    expect($draft->fresh()->name)->toBe('Tarombo Utama');

    $this->actingAs($user)
        ->patch(route('tarombo.compile-drafts.rename', $draft), ['name' => ''])
        ->assertSessionHasErrors('name');

    $this->actingAs(User::factory()->create())
        ->patch(route('tarombo.compile-drafts.rename', $draft), ['name' => 'Bukan milik saya'])
        ->assertNotFound();

    $this->actingAs(User::factory()->create())
        ->post(route('tarombo.compile-drafts.duplicate', $draft), ['name' => 'Salinan'])
        ->assertNotFound();

    expect($draft->fresh()->name)->toBe('Tarombo Utama')
        ->and(TaromboCompileDraft::query()->count())->toBe(1);
});

test('a text layer is saved with its settings and without an image', function () {
    $user = User::factory()->create();
    $snapshot = TaromboSnapshot::factory()->for($user)->create();
    $text = [
        'content' => "Horas!\nKeluarga Besar",
        'font' => 'Cinzel',
        'size' => 42,
        'color' => '#b34b1e',
        'bold' => true,
        'italic' => false,
        'align' => 'center',
    ];

    $this->actingAs($user)->put(route('tarombo.snapshots.compile.draft.update', $snapshot), [
        'state' => compileState([[...compileLayer('layer-1', 'text', 'text'), 'name' => 'Teks 1', 'text' => $text]]),
    ])->assertSessionHasNoErrors();

    $draft = TaromboCompileDraft::query()->sole();

    expect($draft->state['layers'][0]['text'])->toBe($text)
        ->and($draft->state['layers'][0]['source'])->toBe('text');

    $this->actingAs($user)
        ->get(route('tarombo.snapshots.compile', $snapshot))
        ->assertInertia(fn (Assert $page) => $page
            ->where('draft.state.layers.0.text.font', 'Cinzel')
            ->where('draft.state.layers.0.text.content', "Horas!\nKeluarga Besar"));
});

test('an invalid text layer is rejected', function () {
    $user = User::factory()->create();
    $snapshot = TaromboSnapshot::factory()->for($user)->create();
    $text = ['content' => 'Horas', 'font' => 'Cinzel', 'size' => 42, 'color' => '#b34b1e', 'bold' => false, 'italic' => false, 'align' => 'left'];
    $save = fn (array $layer) => $this->actingAs($user)->put(route('tarombo.snapshots.compile.draft.update', $snapshot), [
        'state' => compileState([$layer]),
    ]);

    $save([...compileLayer('layer-1', 'text', 'text'), 'text' => [...$text, 'font' => 'Comic Sans MS']])
        ->assertSessionHasErrors('state.layers.0.text.font');
    $save([...compileLayer('layer-1', 'text', 'text'), 'text' => [...$text, 'color' => 'red']])
        ->assertSessionHasErrors('state.layers.0.text.color');
    $save([...compileLayer('layer-1', 'text', 'text'), 'text' => [...$text, 'content' => str_repeat('a', 501)]])
        ->assertSessionHasErrors('state.layers.0.text.content');
    $save(compileLayer('layer-1', 'text', 'text'))
        ->assertSessionHasErrors('state.layers.0.text');
    // An image layer cannot pretend to be text.
    $save([...compileLayer('layer-1', 'text'), 'text' => $text])
        ->assertSessionHasErrors('state.layers.0.source');

    expect(TaromboCompileDraft::query()->exists())->toBeFalse();
});

test('an arrangement saved before previews were per-draft still shows its composed look', function () {
    $user = User::factory()->create();
    $snapshot = TaromboSnapshot::factory()->for($user)->create();

    $this->actingAs($user)->put(route('tarombo.snapshots.compile.draft.update', $snapshot), [
        'state' => compileState([compileLayer('layer-1', 'upload:0')], ['frame_id' => TaromboFrame::factory()->create()->id]),
        'images' => [UploadedFile::fake()->image('a.png')],
        'preview' => UploadedFile::fake()->image('preview.jpg'),
    ])->assertRedirect();

    $draft = TaromboCompileDraft::query()->sole();

    // Such an arrangement kept its preview in one shared file, not its own.
    Storage::disk('local')->put($draft->legacyPreviewPath(), 'legacy');
    Storage::disk('local')->delete($draft->previewPath());

    expect($draft->existingPreviewPath())->toBe($draft->legacyPreviewPath());

    $this->actingAs($user)
        ->get(route('tarombo.snapshots.index', ['filter' => 'saved']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('snapshots.data.0.draft_id', $draft->id)
            ->where('snapshots.data.0.saved_compile', true)
            ->where('snapshots.data.0.has_preview', true)
            ->where('snapshots.data.0.image_url', fn (string $url) => str_starts_with(
                $url,
                route('tarombo.compile-drafts.preview', $draft),
            )));

    // The route serves the shared file, so the thumbnail does not 404.
    $this->actingAs($user)
        ->get(route('tarombo.compile-drafts.preview', $draft))
        ->assertOk();
});

test('a per-draft preview wins over the shared one left by an older save', function () {
    $user = User::factory()->create();
    $snapshot = TaromboSnapshot::factory()->for($user)->create();

    $this->actingAs($user)->put(route('tarombo.snapshots.compile.draft.update', $snapshot), [
        'state' => compileState([compileLayer('layer-1', 'upload:0')], ['frame_id' => TaromboFrame::factory()->create()->id]),
        'images' => [UploadedFile::fake()->image('a.png')],
        'preview' => UploadedFile::fake()->image('preview.jpg'),
    ])->assertRedirect();

    $draft = TaromboCompileDraft::query()->sole();
    Storage::disk('local')->put($draft->legacyPreviewPath(), 'legacy');

    expect($draft->existingPreviewPath())->toBe($draft->previewPath());
});

test('duplicating an older arrangement gives the copy a preview of its own', function () {
    $user = User::factory()->create();
    $snapshot = TaromboSnapshot::factory()->for($user)->create();

    $this->actingAs($user)->put(route('tarombo.snapshots.compile.draft.update', $snapshot), [
        'state' => compileState([compileLayer('layer-1', 'upload:0')], ['frame_id' => TaromboFrame::factory()->create()->id]),
        'images' => [UploadedFile::fake()->image('a.png')],
        'preview' => UploadedFile::fake()->image('preview.jpg'),
    ])->assertRedirect();

    $original = TaromboCompileDraft::query()->sole();
    Storage::disk('local')->put($original->legacyPreviewPath(), 'legacy');
    Storage::disk('local')->delete($original->previewPath());

    $this->actingAs($user)
        ->post(route('tarombo.compile-drafts.duplicate', $original), ['name' => 'Salinan lama'])
        ->assertRedirect();

    $copy = TaromboCompileDraft::query()->whereKeyNot($original->id)->sole();

    Storage::disk('local')->assertExists($copy->previewPath());
    Storage::disk('local')->assertExists($original->legacyPreviewPath());
});

test('saving another arrangement leaves the shared preview in place', function () {
    $user = User::factory()->create();
    $snapshot = TaromboSnapshot::factory()->for($user)->create();
    $frame = TaromboFrame::factory()->create();

    $this->actingAs($user)->put(route('tarombo.snapshots.compile.draft.update', $snapshot), [
        'state' => compileState([compileLayer('layer-1', 'upload:0')], ['frame_id' => $frame->id]),
        'images' => [UploadedFile::fake()->image('a.png')],
        'preview' => UploadedFile::fake()->image('preview.jpg'),
    ])->assertRedirect();

    $original = TaromboCompileDraft::query()->sole();
    Storage::disk('local')->put($original->legacyPreviewPath(), 'legacy');
    Storage::disk('local')->delete($original->previewPath());

    // A second, separately named arrangement of the same tree.
    $this->actingAs($user)->put(route('tarombo.snapshots.compile.draft.update', $snapshot), [
        'state' => compileState([], ['frame_id' => $frame->id]),
        'preview' => UploadedFile::fake()->image('preview.jpg'),
    ])->assertRedirect();

    expect(TaromboCompileDraft::query()->count())->toBe(2);
    Storage::disk('local')->assertExists($original->legacyPreviewPath());
    expect($original->existingPreviewPath())->toBe($original->legacyPreviewPath());
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

test('a compile can start on a blank canvas, be saved and be reopened', function () {
    $user = User::factory()->create();
    $frame = TaromboFrame::factory()->create();

    $this->actingAs($user)
        ->get(route('tarombo.compile.blank'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tarombo/snapshot-compile')
            ->where('snapshot', null)
            ->where('draft', null));

    $this->actingAs($user)
        ->put(route('tarombo.compile.blank.draft.update'), [
            'state' => compileState(
                [compileLayer('layer-1', 'upload:0'), compileLayer('layer-2', 'upload:1', 'background')],
                ['frame_id' => $frame->id, 'order' => ['layer-2', 'layer-1']],
            ),
            'images' => [UploadedFile::fake()->image('a.png'), UploadedFile::fake()->image('b.png')],
        ])
        ->assertRedirect();

    $draft = TaromboCompileDraft::query()->sole();
    $uuid = substr($draft->state['layers'][0]['source'], 7);

    expect($draft->tarombo_snapshot_id)->toBeNull();
    Storage::disk('local')->assertExists(TaromboCompileDraft::imagePath($user->id, null, $uuid));

    $this->actingAs($user)
        ->get(route('tarombo.compile.blank', ['draft' => $draft->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('draft.id', $draft->id)
            ->where("draft.image_urls.{$uuid}", route('tarombo.compile.blank.draft.image', $uuid)));

    $this->actingAs($user)->get(route('tarombo.compile.blank.draft.image', $uuid))->assertOk();
});

test('a blank canvas compile is listed under Hasil Simpan', function () {
    $user = User::factory()->create();
    $draft = TaromboCompileDraft::query()->create([
        'user_id' => $user->id,
        'tarombo_snapshot_id' => null,
        'state' => json_decode(compileState(), true),
    ]);

    $this->actingAs($user)
        ->get(route('tarombo.snapshots.index', ['filter' => 'saved']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('snapshots.data', 1)
            ->where('snapshots.data.0.blank_canvas', true)
            ->where('snapshots.data.0.draft_id', $draft->id));
});

test('producing without a tree image creates a compiled result without a source', function () {
    $user = User::factory()->create();
    $frame = TaromboFrame::factory()->create();

    $this->actingAs($user)
        ->post(route('tarombo.snapshots.generate'), [
            'frame_id' => $frame->id,
            'title' => '  Nama Hasil  ',
            'image' => UploadedFile::fake()->image('hasil.jpg'),
        ])
        ->assertRedirect(route('tarombo.snapshots.index'));

    $result = TaromboSnapshot::query()->sole();
    expect($result->title)->toBe('Nama Hasil');

    expect($result->tarombo_frame_id)->toBe($frame->id)
        ->and($result->source_snapshot_id)->toBeNull()
        ->and($result->user_id)->toBe($user->id);
});

test('saved compile search uses its displayed name and stays private', function () {
    $user = User::factory()->create();
    $snapshot = TaromboSnapshot::factory()->for($user)->create(['title' => 'Pohon Silaban']);
    $named = TaromboCompileDraft::query()->create(['user_id' => $user->id, 'tarombo_snapshot_id' => $snapshot->id, 'name' => 'Bona Taon Sihombing', 'state' => []]);
    $fallback = TaromboCompileDraft::query()->create(['user_id' => $user->id, 'tarombo_snapshot_id' => $snapshot->id, 'state' => []]);
    TaromboCompileDraft::query()->create(['user_id' => User::factory()->create()->id, 'tarombo_snapshot_id' => $snapshot->id, 'name' => 'Bona Taon Privat', 'state' => []]);
    $this->actingAs($user)->get(route('tarombo.snapshots.index', ['filter' => 'saved', 'q' => 'taon', 'display' => 'titles']))
        ->assertInertia(fn (Assert $page) => $page->has('snapshots.data', 1)->where('snapshots.data.0.draft_id', $named->id)->where('display', 'titles'));
    $this->get(route('tarombo.snapshots.index', ['filter' => 'saved', 'q' => 'silaban']))
        ->assertInertia(fn (Assert $page) => $page->has('snapshots.data', 1)->where('snapshots.data.0.draft_id', $fallback->id));
});

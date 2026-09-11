<?php

use App\Models\TaromboFrame;
use App\Models\TaromboSnapshot;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

test('only an admin can manage tarombo frame templates', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('tarombo-frames.index'))
        ->assertForbidden();

    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get(route('tarombo-frames.index'))
        ->assertOk();
});

test('an admin can save a jpg frame for AI analysis', function () {
    Storage::fake('local');
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->post(route('tarombo-frames.store'), [
            'name' => 'Bingkai Keluarga',
            'image' => UploadedFile::fake()->image('frame.jpg', 1200, 800),
            'is_active' => true,
        ])
        ->assertRedirect();

    $frame = TaromboFrame::query()->sole();

    expect($frame->canvas_width)->toBe(1200)
        ->and($frame->canvas_height)->toBe(800)
        ->and($frame->is_active)->toBeTrue()
        ->and($frame->area_x)->toBe(0)
        ->and($frame->area_y)->toBe(0)
        ->and($frame->area_width)->toBe(1200)
        ->and($frame->area_height)->toBe(800);
    Storage::disk('local')->assertExists($frame->path);
});

test('an account can generate a private framed tarombo only from its own snapshot and an active frame', function () {
    Storage::fake('local');
    config()->set('services.openai.api_key', 'test-api-key');
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $sourcePath = UploadedFile::fake()
        ->image('source.jpg', 1200, 800)
        ->store('tarombo-snapshots/'.$owner->id, 'local');
    $framePath = UploadedFile::fake()
        ->image('frame.jpg', 1600, 1000)
        ->store('tarombo-frames', 'local');
    $snapshot = TaromboSnapshot::factory()->for($owner)->create([
        'path' => $sourcePath,
        'view' => 'tree',
    ]);
    $frame = TaromboFrame::factory()->create([
        'path' => $framePath,
        'canvas_width' => 1600,
        'canvas_height' => 1000,
        'area_x' => 200,
        'area_y' => 100,
        'area_width' => 1200,
        'area_height' => 800,
        'is_active' => true,
    ]);
    Http::fake([
        'api.openai.com/v1/images/edits' => Http::response([
            'data' => [['b64_json' => base64_encode(Storage::disk('local')->get($sourcePath))]],
        ]),
    ]);

    $this->actingAs($owner)
        ->post(route('tarombo.snapshots.generate'), [
            'snapshot_id' => $snapshot->id,
            'frame_id' => $frame->id,
        ])
        ->assertRedirect();

    $generated = TaromboSnapshot::query()->latest('id')->firstOrFail();

    expect($generated->id)->not->toBe($snapshot->id)
        ->and($generated->user_id)->toBe($owner->id)
        ->and($generated->tarombo_frame_id)->toBe($frame->id)
        ->and($generated->view)->toBe('tree');
    Storage::disk('local')->assertExists($generated->path);
    expect(getimagesize(Storage::disk('local')->path($generated->path)))->toMatchArray([0 => 1200, 1 => 800]);

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://api.openai.com/v1/images/edits'
            && $request->method() === 'POST';
    });

    $this->actingAs($otherUser)
        ->post(route('tarombo.snapshots.generate'), [
            'snapshot_id' => $snapshot->id,
            'frame_id' => $frame->id,
        ])
        ->assertNotFound();

    $frame->update(['is_active' => false]);

    $this->actingAs($owner)
        ->post(route('tarombo.snapshots.generate'), [
            'snapshot_id' => $snapshot->id,
            'frame_id' => $frame->id,
        ])
        ->assertNotFound();
});

test('a frame image is available to signed-in users only while the frame is active', function () {
    Storage::fake('local');
    $path = UploadedFile::fake()->image('frame.jpg')->store('tarombo-frames', 'local');
    $frame = TaromboFrame::factory()->create(['path' => $path]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('tarombo-frames.image', $frame))
        ->assertOk()
        ->assertHeader('Cache-Control', 'max-age=0, no-store, private');

    $frame->update(['is_active' => false]);

    $this->actingAs($user)
        ->get(route('tarombo-frames.image', $frame))
        ->assertNotFound();
});

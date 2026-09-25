<?php

use App\Models\TaromboFrame;
use App\Models\TaromboSnapshot;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

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

test('an admin can save a jpg frame with the whole image as its content area', function () {
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

function storeSolidJpeg(string $path, int $width, int $height, array $rgb): string
{
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, imagecolorallocate($image, ...$rgb));
    ob_start();
    imagejpeg($image, null, 95);
    Storage::disk('local')->put($path, (string) ob_get_clean());
    imagedestroy($image);

    return $path;
}

test('an account can save a compiled tarombo frame image for its own snapshot and an active frame', function () {
    Storage::fake('local');
    Http::fake();
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $snapshot = TaromboSnapshot::factory()->for($owner)->create(['view' => 'tree']);
    $frame = TaromboFrame::factory()->create(['is_active' => true]);
    $compiled = fn () => UploadedFile::fake()->image('tarombo-frame.jpg', 1600, 1000);

    $this->actingAs($owner)
        ->post(route('tarombo.snapshots.generate'), [
            'snapshot_id' => $snapshot->id,
            'frame_id' => $frame->id,
            'image' => $compiled(),
        ])
        ->assertRedirect(route('tarombo.snapshots.index'))
        ->assertSessionHasNoErrors();

    $generated = TaromboSnapshot::query()->latest('id')->firstOrFail();

    expect($generated->id)->not->toBe($snapshot->id)
        ->and($generated->user_id)->toBe($owner->id)
        ->and($generated->tarombo_frame_id)->toBe($frame->id)
        ->and($generated->view)->toBe('tree')
        ->and($generated->path)->toStartWith("tarombo-snapshots/{$owner->id}/");
    Storage::disk('local')->assertExists($generated->path);
    Http::assertNothingSent();

    $this->actingAs($owner)
        ->post(route('tarombo.snapshots.generate'), [
            'snapshot_id' => $snapshot->id,
            'frame_id' => $frame->id,
        ])
        ->assertSessionHasErrors('image');

    $this->actingAs($otherUser)
        ->post(route('tarombo.snapshots.generate'), [
            'snapshot_id' => $snapshot->id,
            'frame_id' => $frame->id,
            'image' => $compiled(),
        ])
        ->assertNotFound();

    $frame->update(['is_active' => false]);

    $this->actingAs($owner)
        ->post(route('tarombo.snapshots.generate'), [
            'snapshot_id' => $snapshot->id,
            'frame_id' => $frame->id,
            'image' => $compiled(),
        ])
        ->assertNotFound();
});

test('only an admin can set the content area of a frame, inside the frame bounds', function () {
    $frame = TaromboFrame::factory()->create([
        'canvas_width' => 1600,
        'canvas_height' => 1000,
    ]);
    $area = ['area_x' => 200, 'area_y' => 100, 'area_width' => 1200, 'area_height' => 800];

    $this->actingAs(User::factory()->create())
        ->put(route('tarombo-frames.area.update', $frame), $area)
        ->assertForbidden();

    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->put(route('tarombo-frames.area.update', $frame), [...$area, 'area_width' => 1500])
        ->assertSessionHasErrors('area_width');

    $this->actingAs($admin)
        ->put(route('tarombo-frames.area.update', $frame), $area)
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($frame->fresh()->only(array_keys($area)))->toBe($area);
});

test('replacing a frame image keeps the content area proportional', function () {
    Storage::fake('local');
    $admin = User::factory()->create(['role' => 'admin']);
    $frame = TaromboFrame::factory()->create([
        'path' => storeSolidJpeg('tarombo-frames/old.jpg', 1600, 1000, [0, 0, 255]),
        'canvas_width' => 1600,
        'canvas_height' => 1000,
        'area_x' => 200,
        'area_y' => 100,
        'area_width' => 1200,
        'area_height' => 800,
    ]);

    $this->actingAs($admin)
        ->put(route('tarombo-frames.update', $frame), [
            'name' => 'Bingkai Baru',
            'image' => UploadedFile::fake()->image('frame.jpg', 800, 500),
            'is_active' => true,
        ])
        ->assertRedirect();

    expect($frame->fresh()->only(['canvas_width', 'canvas_height', 'area_x', 'area_y', 'area_width', 'area_height']))
        ->toBe(['canvas_width' => 800, 'canvas_height' => 500, 'area_x' => 100, 'area_y' => 50, 'area_width' => 600, 'area_height' => 400]);
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

test('the compile page previews an own snapshot with active non-collage frames only', function () {
    $owner = User::factory()->create(['name' => 'Pemilik']);
    $snapshot = TaromboSnapshot::factory()->for($owner)->create();
    $activeFrame = TaromboFrame::factory()->create(['is_active' => true]);
    TaromboFrame::factory()->create(['is_active' => false]);
    TaromboFrame::factory()->create(['is_active' => true, 'is_collage' => true]);

    $this->actingAs($owner)
        ->get(route('tarombo.snapshots.compile', $snapshot))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tarombo/snapshot-compile')
            ->where('snapshot.id', $snapshot->id)
            ->where('accountName', 'Pemilik')
            ->has('frames', 1)
            ->where('frames.0.id', $activeFrame->id)
            ->has('frames.0.area_width'));

    $this->actingAs(User::factory()->create())
        ->getJson(route('tarombo.snapshots.compile', $snapshot))
        ->assertForbidden();
});

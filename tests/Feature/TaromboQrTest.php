<?php

use App\Models\TaromboFrame;
use App\Models\TaromboSnapshot;
use App\Models\User;
use App\Services\TaromboQrComposer;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('local');
    $this->admin = User::factory()->asAdmin()->create();
    $this->recipient = User::factory()->create();
    $this->original = TaromboSnapshot::factory()->for($this->admin)->create([
        'tarombo_frame_id' => TaromboFrame::factory()->create()->id,
        'title' => 'Pohon Borsak Junjungan',
    ]);
    $image = imagecreatetruecolor(1200, 800);
    imagefill($image, 0, 0, imagecolorallocate($image, 200, 20, 30));
    Storage::disk('local')->put($this->original->path, app(TaromboQrComposer::class)->png($image));
    imagedestroy($image);
    $this->qrData = ['user_id' => $this->recipient->id, 'token' => (string) Str::uuid(), 'x' => 100, 'y' => 100, 'size' => 18];
});

test('admin can pick a destination account and preview a QR', function () {
    $this->actingAs($this->admin)->get(route('tarombo.qr.create', $this->original))
        ->assertInertia(fn (Assert $page) => $page->component('tarombo/attach-qr')
            ->where('hasPreview', true)->where('snapshot.title', 'Pohon Borsak Junjungan')->has('users', 2)
            ->where('token', fn ($token) => Str::isUuid($token))
            ->where('qrImageUrl', fn ($url) => str_starts_with($url, 'data:image/png;base64,') && getimagesizefromstring(base64_decode(substr($url, strlen('data:image/png;base64,')))) !== false)
            ->where('imageSize.width', 1200)->where('imageSize.height', 800));
    $this->get(route('tarombo.qr.code', ['token' => $this->qrData['token']]))->assertSuccessful()->assertHeader('Content-Type', 'image/png');
});

test('QR composition saves a new compiled image to the selected account without changing source', function () {
    $before = Storage::disk('local')->get($this->original->path);
    $this->actingAs($this->admin)->post(route('tarombo.qr.store', $this->original), $this->qrData)->assertSessionHasNoErrors()->assertRedirect();
    $result = TaromboSnapshot::query()->where('qr_token', $this->qrData['token'])->sole();
    expect($result->user_id)->toBe($this->recipient->id)
        ->and($result->tarombo_frame_id)->toBe($this->original->tarombo_frame_id)
        ->and($result->title)->toBe('Pohon Borsak Junjungan · QR')
        ->and(Storage::disk('local')->get($this->original->path))->toBe($before)
        ->and($this->original->fresh()->qr_token)->toBeNull();
    $image = imagecreatefromstring(Storage::disk('local')->get($result->path));
    expect(imagesx($image))->toBe(1200)->and(imagesy($image))->toBe(800)
        ->and(imagecolorat($image, 0, 0))->toBe((200 << 16) + (20 << 8) + 30)
        ->and(imagecolorat($image, 1199, 799))->toBe(0xFFFFFF);
    imagedestroy($image);
    $this->actingAs($this->recipient)->get(route('tarombo.snapshots.index', ['filter' => 'compiled']))
        ->assertInertia(fn (Assert $page) => $page->has('snapshots.data', 1)->where('snapshots.data.0.id', $result->id));
    $this->actingAs(User::factory()->create())->get(route('tarombo.snapshots.index', ['filter' => 'compiled']))
        ->assertInertia(fn (Assert $page) => $page->has('snapshots.data', 0));
});

test('scan URL opens a public viewer with zoom image and download without login', function () {
    $result = app(TaromboQrComposer::class)->attach($this->original, $this->recipient, $this->qrData);
    $token = $this->qrData['token'];
    $this->get(route('tarombo.qr.show', ['token' => $token]))->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->component('tarombo/qr-result')
            ->where('snapshot.title', $result->title)
            ->where('snapshot.image_url', route('tarombo.qr.image', ['token' => $token]))
            ->where('snapshot.download_url', route('tarombo.qr.download', ['token' => $token])));
    $this->get(route('tarombo.qr.image', ['token' => $token]))->assertSuccessful()->assertHeader('Content-Type', 'image/png');
    $this->get(route('tarombo.qr.download', ['token' => $token]))->assertSuccessful()
        ->assertHeader('Content-Type', 'image/png')->assertHeader('Content-Disposition', 'attachment; filename=pohon-borsak-junjungan-qr.png');
    $this->get(route('tarombo.snapshots.image', $result))->assertRedirect(route('login'));
    $this->get(route('tarombo.qr.show', ['token' => (string) Str::uuid()]))->assertNotFound();
    $result->delete();
    $this->get(route('tarombo.qr.show', ['token' => $token]))->assertNotFound();
    $this->get(route('tarombo.qr.download', ['token' => $token]))->assertNotFound();
});

test('only admins can attach QR or access the account selection', function (string $state) {
    $factory = User::factory();
    $user = ($state === 'user' ? $factory : $factory->{$state}())->create();
    $this->actingAs($user)->get(route('tarombo.qr.create', $this->original))->assertForbidden();
    $this->post(route('tarombo.qr.store', $this->original), $this->qrData)->assertForbidden();
    expect(TaromboSnapshot::query()->whereNotNull('qr_token')->exists())->toBeFalse();
})->with(['user', 'asSubAdmin']);

test('QR can only be attached to a compiled image', function () {
    $raw = TaromboSnapshot::factory()->for($this->admin)->create(['tarombo_frame_id' => null]);
    $this->actingAs($this->admin)->get(route('tarombo.qr.create', $raw))->assertNotFound();
    $this->post(route('tarombo.qr.store', $raw), $this->qrData)->assertNotFound();
});

test('QR attachment requires recipient and valid placement and cannot reuse a link', function () {
    $this->actingAs($this->admin)->post(route('tarombo.qr.store', $this->original), [...$this->qrData, 'user_id' => 999999, 'x' => -1, 'y' => 101, 'size' => 0])->assertSessionHasErrors(['user_id', 'x', 'y', 'size']);
    $this->post(route('tarombo.qr.store', $this->original), $this->qrData)->assertSessionHasNoErrors();
    $this->post(route('tarombo.qr.store', $this->original), $this->qrData)->assertSessionHasErrors('token');
    expect(TaromboSnapshot::query()->whereNotNull('qr_token')->count())->toBe(1);
});

test('missing preview does not create a broken result', function () {
    Storage::disk('local')->delete($this->original->path);
    $this->actingAs($this->admin)->post(route('tarombo.qr.store', $this->original), $this->qrData)->assertSessionHasErrors('image');
    expect(TaromboSnapshot::query()->whereNotNull('qr_token')->exists())->toBeFalse();
});

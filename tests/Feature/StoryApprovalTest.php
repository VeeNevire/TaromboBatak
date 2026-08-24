<?php

use App\Models\Event;
use App\Models\Marga;
use App\Models\Story;
use App\Models\User;
use App\Notifications\EventSubmitted;
use App\Notifications\StorySubmitted;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

function storyPayload(array $overrides = []): array
{
    return [
        'title' => 'Asal Usul Tradisi Batak',
        'description' => 'Catatan sejarah dan budaya keluarga Batak.',
        'image' => 'https://example.com/cerita.jpg',
        'classification' => 'umum',
        'published' => true,
        ...$overrides,
    ];
}

test('an ordinary user submits a pending general story to all contributors', function () {
    Notification::fake();
    $marga = Marga::factory()->create();
    $otherMarga = Marga::factory()->create();
    $user = User::factory()->withMarga($marga->id)->create();
    $firstContributor = User::factory()->asMainContributor()->withMarga($marga->id)->create();
    $secondContributor = User::factory()->asContributorMember()->withMarga($otherMarga->id)->create();

    $this->actingAs($user)
        ->post(route('stories.store'), storyPayload())
        ->assertRedirect(route('stories.index'))
        ->assertSessionHasNoErrors();

    $story = Story::firstOrFail();

    expect($story->created_by)->toBe($user->id)
        ->and($story->classification)->toBe(Story::CLASSIFICATION_GENERAL)
        ->and($story->marga_id)->toBeNull()
        ->and($story->status)->toBe(Story::STATUS_PENDING)
        ->and($story->published)->toBeFalse();

    Notification::assertSentTo([$firstContributor, $secondContributor], StorySubmitted::class);
});

test('a user without a marga can submit a general story but not a marga story', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('stories.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('canCreateMarga', false)
            ->where('canChooseMarga', false));

    $this->actingAs($user)
        ->post(route('stories.store'), storyPayload())
        ->assertRedirect(route('stories.index'));

    expect(Story::firstOrFail()->status)->toBe(Story::STATUS_PENDING);

    $this->actingAs($user)
        ->post(route('stories.store'), storyPayload([
            'title' => 'Cerita Marga Tanpa Marga',
            'classification' => 'marga',
        ]))
        ->assertForbidden();
});

test('a marga story is bound to the ordinary users marga and notifies only matching contributors', function () {
    Notification::fake();
    $marga = Marga::factory()->create();
    $otherMarga = Marga::factory()->create();
    $user = User::factory()->withMarga($marga->id)->create();
    $matching = User::factory()->asMainContributor()->withMarga($marga->id)->create();
    $outsider = User::factory()->asMainContributor()->withMarga($otherMarga->id)->create();

    $this->actingAs($user)->post(route('stories.store'), storyPayload([
        'classification' => 'marga',
        'marga_id' => $otherMarga->id,
    ]))->assertRedirect(route('stories.index'));

    $story = Story::firstOrFail();

    expect($story->marga_id)->toBe($marga->id)
        ->and($story->classification)->toBe(Story::CLASSIFICATION_MARGA);
    Notification::assertSentTo($matching, StorySubmitted::class);
    Notification::assertNotSentTo($outsider, StorySubmitted::class);
});

test('a contributor creates an approved story', function () {
    $marga = Marga::factory()->create();
    $contributor = User::factory()->asContributorMember()->withMarga($marga->id)->create();

    $this->actingAs($contributor)
        ->post(route('stories.store'), storyPayload(['classification' => 'marga']))
        ->assertRedirect(route('stories.index'));

    $story = Story::firstOrFail();

    expect($story->status)->toBe(Story::STATUS_APPROVED)
        ->and($story->published)->toBeTrue()
        ->and($story->marga_id)->toBe($marga->id);
});

test('all contributors can approve general stories but marga stories are scoped', function () {
    $marga = Marga::factory()->create();
    $otherMarga = Marga::factory()->create();
    $reviewer = User::factory()->asMainContributor()->withMarga($marga->id)->create();
    $general = Story::factory()->pending()->create(['classification' => 'umum', 'marga_id' => null]);
    $scoped = Story::factory()->pending()->create(['classification' => 'marga', 'marga_id' => $otherMarga->id]);

    $this->actingAs($reviewer)
        ->post(route('stories.approve', $general), ['version' => $general->review_version])
        ->assertRedirect(route('contributions.index', ['tab' => 'stories']));

    $this->actingAs($reviewer)
        ->post(route('stories.approve', $scoped), ['version' => $scoped->review_version])
        ->assertForbidden();

    expect($general->fresh()->status)->toBe(Story::STATUS_APPROVED)
        ->and($scoped->fresh()->status)->toBe(Story::STATUS_PENDING);
});

test('pending stories are listed in contributions by scope and globally for admins', function () {
    $marga = Marga::factory()->create();
    $otherMarga = Marga::factory()->create();
    $contributor = User::factory()->asMainContributor()->withMarga($marga->id)->create();
    $admin = User::factory()->asAdmin()->create();
    Story::factory()->pending()->create(['classification' => 'umum', 'marga_id' => null]);
    Story::factory()->pending()->create(['classification' => 'marga', 'marga_id' => $marga->id]);
    Story::factory()->pending()->create(['classification' => 'marga', 'marga_id' => $otherMarga->id]);
    Story::factory()->create(['classification' => 'marga', 'marga_id' => $marga->id]);

    $this->actingAs($contributor)->get(route('contributions.index', ['tab' => 'stories']))
        ->assertInertia(fn (Assert $page) => $page->has('storyRequests.data', 2));

    $this->actingAs($admin)->get(route('contributions.index', ['tab' => 'stories']))
        ->assertInertia(fn (Assert $page) => $page->has('storyRequests.data', 3));
});

test('story lists and management permissions follow ownership and role', function () {
    $marga = Marga::factory()->create();
    $otherMarga = Marga::factory()->create();
    $user = User::factory()->withMarga($marga->id)->create();
    $otherUser = User::factory()->withMarga($marga->id)->create();
    $contributor = User::factory()->asMainContributor()->withMarga($marga->id)->create();
    $admin = User::factory()->asAdmin()->create();
    $owned = Story::factory()->create(['created_by' => $user->id, 'classification' => 'marga', 'marga_id' => $marga->id]);
    Story::factory()->create(['classification' => 'umum', 'marga_id' => null]);
    Story::factory()->create(['classification' => 'marga', 'marga_id' => $otherMarga->id]);

    $this->actingAs($user)->get(route('stories.index'))
        ->assertInertia(fn (Assert $page) => $page->has('stories.data', 1));
    $this->actingAs($contributor)->get(route('stories.index'))
        ->assertInertia(fn (Assert $page) => $page->has('stories.data', 2));
    $this->actingAs($admin)->get(route('stories.index'))
        ->assertInertia(fn (Assert $page) => $page->has('stories.data', 3));

    expect($user->can('update', $owned))->toBeTrue()
        ->and($otherUser->can('update', $owned))->toBeFalse()
        ->and($admin->can('delete', $owned))->toBeTrue();
});

test('editing an approved story as an ordinary user requires approval again', function () {
    Notification::fake();
    $marga = Marga::factory()->create();
    $user = User::factory()->withMarga($marga->id)->create();
    $contributor = User::factory()->asMainContributor()->withMarga($marga->id)->create();
    $story = Story::factory()->create([
        'created_by' => $user->id,
        'classification' => 'marga',
        'marga_id' => $marga->id,
    ]);

    $this->actingAs($user)
        ->put(route('stories.update', $story), storyPayload([
            'title' => 'Cerita Diperbarui',
            'classification' => 'marga',
        ]))
        ->assertRedirect(route('stories.index'));

    expect($story->fresh()->status)->toBe(Story::STATUS_PENDING)
        ->and($story->fresh()->published)->toBeFalse()
        ->and($story->fresh()->review_version)->toBe(2);
    Notification::assertSentTo($contributor, StorySubmitted::class);
});

test('pending stories cannot appear on public story surfaces', function () {
    $story = Story::factory()->pending()->create(['published' => true]);

    $this->get(route('cerita.index'))
        ->assertInertia(fn (Assert $page) => $page->has('stories.data', 0));
    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page->has('stories', 0));
    $this->get(route('cerita.show', $story))->assertNotFound();
});

test('a contributor can reject an in-scope story with a reason', function () {
    $marga = Marga::factory()->create();
    $reviewer = User::factory()->asContributorMember()->withMarga($marga->id)->create();
    $story = Story::factory()->pending()->create([
        'classification' => 'marga',
        'marga_id' => $marga->id,
    ]);

    $this->actingAs($reviewer)
        ->post(route('stories.reject', $story), [
            'reason' => 'Sumber cerita belum dicantumkan.',
            'version' => $story->review_version,
        ])
        ->assertRedirect(route('contributions.index', ['tab' => 'stories']));

    expect($story->fresh()->status)->toBe(Story::STATUS_REJECTED)
        ->and($story->fresh()->published)->toBeFalse()
        ->and($story->fresh()->reviewed_by)->toBe($reviewer->id)
        ->and($story->fresh()->rejection_reason)->toBe('Sumber cerita belum dicantumkan.');
});

test('a reviewer cannot approve a stale story version', function () {
    Notification::fake();
    $marga = Marga::factory()->create();
    $user = User::factory()->withMarga($marga->id)->create();
    $reviewer = User::factory()->asMainContributor()->withMarga($marga->id)->create();
    $story = Story::factory()->pending()->create([
        'created_by' => $user->id,
        'classification' => 'marga',
        'marga_id' => $marga->id,
    ]);
    $staleVersion = $story->review_version;

    $this->actingAs($user)
        ->put(route('stories.update', $story), storyPayload([
            'title' => 'Konten Cerita Terbaru',
            'classification' => 'marga',
        ]))
        ->assertRedirect(route('stories.index'));

    $this->actingAs($reviewer)
        ->post(route('stories.approve', $story), ['version' => $staleVersion])
        ->assertRedirect(route('contributions.index', ['tab' => 'stories']));

    expect($story->fresh()->status)->toBe(Story::STATUS_PENDING)
        ->and($story->fresh()->review_version)->toBe($staleVersion + 1);
});

test('a sub-admin editing a pending story cannot bypass approval', function () {
    $marga = Marga::factory()->create();
    $subAdmin = User::factory()->asSubAdmin()->create();
    $story = Story::factory()->pending()->create([
        'classification' => 'marga',
        'marga_id' => $marga->id,
    ]);

    $this->actingAs($subAdmin)
        ->put(route('stories.update', $story), storyPayload([
            'title' => 'Diperbaiki Sub Admin',
            'classification' => 'marga',
            'marga_id' => $marga->id,
        ]))
        ->assertRedirect(route('stories.index'));

    expect($story->fresh()->status)->toBe(Story::STATUS_PENDING)
        ->and($story->fresh()->published)->toBeFalse();
});

test('opening a contribution tab only marks that notification category as read', function () {
    $contributor = User::factory()->asMainContributor()->create();
    $story = Story::factory()->pending()->create();
    $event = Event::factory()->pending()->create();

    $contributor->notify(new StorySubmitted($story));
    $contributor->notify(new EventSubmitted($event));

    $this->actingAs($contributor)
        ->get(route('contributions.index', ['tab' => 'events']))
        ->assertOk();

    expect($contributor->unreadNotifications()->where('type', EventSubmitted::class)->count())->toBe(0)
        ->and($contributor->unreadNotifications()->where('type', StorySubmitted::class)->count())->toBe(1);

    $this->actingAs($contributor)
        ->get(route('contributions.index', ['tab' => 'stories']))
        ->assertOk();

    expect($contributor->unreadNotifications()->where('type', StorySubmitted::class)->count())->toBe(0);
});

test('deleting a pending story clears its contributor notifications', function () {
    $marga = Marga::factory()->create();
    $user = User::factory()->withMarga($marga->id)->create();
    $contributor = User::factory()->asMainContributor()->withMarga($marga->id)->create();

    $this->actingAs($user)
        ->post(route('stories.store'), storyPayload(['classification' => 'marga']))
        ->assertRedirect(route('stories.index'));

    $story = Story::firstOrFail();
    expect($contributor->notifications()->where('type', StorySubmitted::class)->count())->toBe(1);

    $this->actingAs($user)
        ->delete(route('stories.destroy', $story))
        ->assertRedirect(route('stories.index'));

    expect($contributor->notifications()->where('type', StorySubmitted::class)->count())->toBe(0)
        ->and(Story::find($story->id))->toBeNull();
});

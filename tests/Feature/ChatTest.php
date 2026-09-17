<?php

use App\Events\MessageRead;
use App\Events\MessageSent;
use App\Models\ContactDisconnect;
use App\Models\ContactRequest;
use App\Models\Conversation;
use App\Models\Marga;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\Person;
use App\Models\TelegramAccount;
use App\Models\User;
use App\Services\TelegramMtproto;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('contacts only contain other non-admin accounts from the same marga', function () {
    $marga = Marga::factory()->create();
    $otherMarga = Marga::factory()->create();
    $user = User::factory()->withMarga($marga->id)->create();
    $relative = User::factory()->withMarga($marga->id)->create(['name' => 'Anggota Marga']);
    $caretaker = User::factory()->asSubAdmin()->withMarga($marga->id)->create(['name' => 'Pengurus Marga']);
    User::factory()->asAdmin()->withMarga($marga->id)->create();
    User::factory()->withMarga($otherMarga->id)->create();

    $this->actingAs($user)
        ->get(route('contacts.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('contacts/index')
            ->has('contacts', 2)
            ->where('contacts.0.name', 'Anggota Marga')
            ->where('contacts.0.role_label', 'Anggota Marga')
            ->where('contacts.1.name', 'Pengurus Marga')
            ->where('contacts.1.role_label', 'Pengurus Marga')
            ->where('selectedContact', null)
            ->where('messages', []));
});

test('main administrators cannot use ordinary contacts', function () {
    $admin = User::factory()->asAdmin()->create();

    $this->actingAs($admin)->get(route('contacts.index'))->assertForbidden();
});

test('users without a marga do not see other unassigned accounts as contacts', function () {
    $user = User::factory()->create();
    User::factory()->create();

    $this->actingAs($user)
        ->get(route('contacts.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('contacts/index')
            ->has('contacts', 0));
});

test('users cannot open or message contacts outside their marga', function () {
    $user = User::factory()->withMarga(Marga::factory()->create()->id)->create();
    $outsider = User::factory()->withMarga(Marga::factory()->create()->id)->create();

    $this->actingAs($user)->get(route('contacts.show', $outsider))->assertForbidden();
    $this->actingAs($user)
        ->post(route('contacts.messages.store', $outsider), ['body' => 'Tidak boleh terkirim'])
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data.toast.type', 'error');

    expect(Conversation::query()->count())->toBe(0)
        ->and(Message::query()->count())->toBe(0);
});

test('a user can disconnect a contact and loses access to their conversation', function () {
    $marga = Marga::factory()->create();
    $user = User::factory()->withMarga($marga->id)->create();
    $contact = User::factory()->withMarga($marga->id)->create();

    $this->actingAs($user)
        ->delete(route('contacts.destroy', $contact))
        ->assertRedirect(route('contacts.index'));

    $disconnect = ContactDisconnect::query()->sole();
    $this->assertModelExists($disconnect);

    $this->actingAs($user)
        ->get(route('contacts.index'))
        ->assertInertia(fn (Assert $page) => $page->has('contacts', 0));
    $this->actingAs($user)
        ->get(route('contacts.show', $contact))
        ->assertForbidden();
});

test('a disconnected contact remains hidden until their reconnection request is approved', function () {
    $marga = Marga::factory()->create();
    $user = User::factory()->withMarga($marga->id)->create();
    $contact = User::factory()->withMarga($marga->id)->create(['name' => 'Tunggul']);

    $this->actingAs($user)
        ->delete(route('contacts.destroy', $contact))
        ->assertRedirect(route('contacts.index'));

    $this->actingAs($user)
        ->post(route('contact-requests.store'), ['recipient_id' => $contact->id])
        ->assertRedirect();

    expect(ContactDisconnect::query()
        ->where(ContactDisconnect::attributesFor($user->id, $contact->id))
        ->exists())->toBeTrue()
        ->and(ContactRequest::query()->where([
            'requester_id' => $user->id,
            'recipient_id' => $contact->id,
            'status' => ContactRequest::STATUS_PENDING,
        ])->exists())->toBeTrue();

    $this->actingAs($user)
        ->get(route('contacts.index'))
        ->assertInertia(fn (Assert $page) => $page->has('contacts', 0));

    $request = ContactRequest::query()->where([
        'requester_id' => $user->id,
        'recipient_id' => $contact->id,
    ])->firstOrFail();

    $this->actingAs($contact)
        ->patch(route('contact-requests.update', $request), ['status' => ContactRequest::STATUS_APPROVED])
        ->assertRedirect();

    expect(ContactDisconnect::query()
        ->where(ContactDisconnect::attributesFor($user->id, $contact->id))
        ->exists())->toBeFalse();

    $this->actingAs($user)
        ->get(route('contacts.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('contacts', 1)
            ->where('contacts.0.name', 'Tunggul'));
});

test('incoming contact requests include the requester profile for the contact modal', function () {
    $marga = Marga::factory()->create(['name' => 'Sitorus', 'color' => '#2255aa']);
    $recipient = User::factory()->withMarga($marga->id)->create();
    $father = Person::factory()->create(['name' => 'Tunggul Sitorus']);
    $contributor = User::factory()->create(['name' => 'Rehan2']);
    $person = Person::factory()->create([
        'name' => 'Borsak Sitorus',
        'alias' => 'Borsak',
        'marga_id' => $marga->id,
        'image' => 'https://example.com/borsak.jpg',
        'birth_year' => '1985',
        'bio' => 'Anggota keluarga Sitorus.',
        'father_id' => $father->id,
        'spouse' => 'Risma Siahaan',
        'related_stories' => [['title' => 'Sejarah Borsak', 'url' => 'https://example.com/sejarah-borsak']],
        'created_by' => $contributor->id,
    ]);
    Person::factory()->create(['name' => 'Anak Borsak', 'father_id' => $person->id]);
    $requester = User::factory()->asSubAdmin()->withMarga($marga->id)->create([
        'current_person_id' => $person->id,
        'name' => 'Akun Borsak',
    ]);
    $request = ContactRequest::query()->create([
        'requester_id' => $requester->id,
        'recipient_id' => $recipient->id,
        'status' => ContactRequest::STATUS_PENDING,
    ]);

    $this->actingAs($recipient)
        ->get(route('contacts.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->has('incomingContactRequests', 1)
            ->where('incomingContactRequests.0.id', $request->id)
            ->where('incomingContactRequests.0.user_id', $requester->id)
            ->where('incomingContactRequests.0.name', 'Akun Borsak')
            ->where('incomingContactRequests.0.person_name', 'Borsak Sitorus')
            ->where('incomingContactRequests.0.person_alias', 'Borsak')
            ->where('incomingContactRequests.0.person_image', 'https://example.com/borsak.jpg')
            ->where('incomingContactRequests.0.birth_year', '1985')
            ->where('incomingContactRequests.0.bio', 'Anggota keluarga Sitorus.')
            ->where('incomingContactRequests.0.father_name', 'Tunggul Sitorus')
            ->where('incomingContactRequests.0.spouse', 'Risma Siahaan')
            ->where('incomingContactRequests.0.children.0', 'Anak Borsak')
            ->where('incomingContactRequests.0.related_stories.0.title', 'Sejarah Borsak')
            ->where('incomingContactRequests.0.related_stories.0.url', 'https://example.com/sejarah-borsak')
            ->where('incomingContactRequests.0.contributor', 'Rehan2')
            ->where('incomingContactRequests.0.marga', 'Sitorus')
            ->where('incomingContactRequests.0.color', '#2255aa')
            ->where('incomingContactRequests.0.role_label', 'Pengurus Marga'));
});

test('the incremental messages feed returns only newer messages as json', function () {
    $marga = Marga::factory()->create();
    $reader = User::factory()->withMarga($marga->id)->create();
    $sender = User::factory()->withMarga($marga->id)->create();

    $conversation = Conversation::create(
        Conversation::participantAttributes($reader, $sender),
    );
    $first = $conversation->messages()->create(['sender_id' => $sender->id, 'body' => 'pertama']);
    $second = $conversation->messages()->create(['sender_id' => $sender->id, 'body' => 'kedua']);

    $this->actingAs($reader)
        ->get(route('contacts.messages.index', [
            'contact' => $sender,
            'after_id' => $first->id,
        ]))
        ->assertOk()
        ->assertJsonCount(1, 'messages')
        ->assertJsonPath('messages.0.id', $second->id)
        ->assertJsonPath('messages.0.is_mine', false);

    // Percakapan terbuka: pesan masuk otomatis ditandai sudah dibaca.
    expect($second->fresh()->read_at)->not->toBeNull()
        ->and($first->fresh()->read_at)->not->toBeNull();
});

test('the messages feed rejects contacts outside the marga', function () {
    $marga = Marga::factory()->create();
    $otherMarga = Marga::factory()->create();
    $user = User::factory()->withMarga($marga->id)->create();
    $outsider = User::factory()->withMarga($otherMarga->id)->create();

    $this->actingAs($user)
        ->get(route('contacts.messages.index', ['contact' => $outsider]))
        ->assertForbidden();
});

test('the messages feed supports history pagination with before_id', function () {
    $marga = Marga::factory()->create();
    $reader = User::factory()->withMarga($marga->id)->create();
    $sender = User::factory()->withMarga($marga->id)->create();

    $conversation = Conversation::create(
        Conversation::participantAttributes($reader, $sender),
    );

    $ids = [];

    foreach (range(1, 5) as $i) {
        $ids[] = $conversation->messages()->create([
            'sender_id' => $sender->id,
            'body' => 'pesan '.$i,
        ])->id;
    }

    // Cursor ke depan: hanya pesan setelah kursor.
    $this->actingAs($reader)
        ->get(route('contacts.messages.index', [
            'contact' => $sender,
            'after_id' => $ids[3],
        ]))
        ->assertOk()
        ->assertJsonCount(1, 'messages')
        ->assertJsonPath('messages.0.body', 'pesan 5');

    // Halaman riwayat: dua pesan sebelum kursor, tertua disembunyikan.
    $this->actingAs($reader)
        ->get(route('contacts.messages.index', [
            'contact' => $sender,
            'before_id' => $ids[4],
            'limit' => 2,
        ]))
        ->assertOk()
        ->assertJsonPath('has_more', true)
        ->assertJsonCount(2, 'messages')
        ->assertJsonPath('messages.0.body', 'pesan 3')
        ->assertJsonPath('messages.1.body', 'pesan 4');
});

test('reading the messages feed broadcasts a read receipt to the sender', function () {
    Event::fake([MessageRead::class]);

    $marga = Marga::factory()->create();
    $reader = User::factory()->withMarga($marga->id)->create();
    $sender = User::factory()->withMarga($marga->id)->create();

    $conversation = Conversation::create(
        Conversation::participantAttributes($reader, $sender),
    );
    $message = $conversation->messages()->create([
        'sender_id' => $sender->id,
        'body' => 'tolong dibaca',
    ]);

    $this->actingAs($reader)
        ->get(route('contacts.messages.index', ['contact' => $sender]))
        ->assertOk();

    Event::assertDispatched(
        MessageRead::class,
        fn (MessageRead $event) => $event->recipientUserId === $sender->id
            && $event->readerId === $reader->id
            && in_array($message->id, $event->messageIds)
            && $event->broadcastOn()[0]->name === (new PrivateChannel('users.'.$sender->id))->name,
    );
});

test('a user can send a validated message to a contact in the same marga', function () {
    $marga = Marga::factory()->create();
    $sender = User::factory()->withMarga($marga->id)->create();
    $recipient = User::factory()->asSubAdmin()->withMarga($marga->id)->create();
    Event::fake([MessageSent::class]);

    $this->actingAs($sender)
        ->post(route('contacts.messages.store', $recipient), ['body' => '  Horas, amang!  '])
        ->assertRedirect(route('contacts.show', $recipient));

    $conversation = Conversation::query()->firstOrFail();
    $message = Message::query()->firstOrFail();

    expect($conversation->user_one_id)->toBe(min($sender->id, $recipient->id))
        ->and($conversation->user_two_id)->toBe(max($sender->id, $recipient->id))
        ->and($message->sender_id)->toBe($sender->id)
        ->and($message->body)->toBe('Horas, amang!');

    Event::assertDispatched(MessageSent::class, fn (MessageSent $event) => $event->message->is($message));
});

test('a user can send a private image attachment without message text', function () {
    Storage::fake('local');

    $marga = Marga::factory()->create();
    $sender = User::factory()->withMarga($marga->id)->create();
    $recipient = User::factory()->withMarga($marga->id)->create();
    Event::fake([MessageSent::class]);

    $this->actingAs($sender)
        ->post(route('contacts.messages.store', $recipient), [
            'body' => '',
            'attachments' => [UploadedFile::fake()->image('keluarga.jpg', 800, 600)],
        ])
        ->assertRedirect(route('contacts.show', $recipient));

    $message = Message::query()->with('attachments')->firstOrFail();
    $attachment = $message->attachments->firstOrFail();

    expect($message->body)->toBeNull()
        ->and($attachment->original_name)->toBe('keluarga.jpg')
        ->and($attachment->category)->toBe('image');
    Storage::disk('local')->assertExists($attachment->path);
    Event::assertDispatched(MessageSent::class);

    $this->actingAs($recipient)
        ->get(route('message-attachments.show', $attachment))
        ->assertSuccessful()
        ->assertHeader('content-type', 'image/jpeg');
});

test('private attachments are also sent to the connected recipient on Telegram', function () {
    Storage::fake('local');

    $marga = Marga::factory()->create();
    $sender = User::factory()->withMarga($marga->id)->create();
    $recipient = User::factory()->withMarga($marga->id)->create();
    TelegramAccount::create([
        'user_id' => $sender->id,
        'telegram_user_id' => 101,
        'private_chat_id' => 101,
        'display_name' => $sender->name,
        'session_path' => 'sessions/sender',
        'connection_status' => TelegramAccount::STATUS_CONNECTED,
        'linked_at' => now(),
    ]);
    TelegramAccount::create([
        'user_id' => $recipient->id,
        'telegram_user_id' => 202,
        'private_chat_id' => 202,
        'display_name' => $recipient->name,
        'username' => 'recipient',
        'session_path' => 'sessions/recipient',
        'connection_status' => TelegramAccount::STATUS_CONNECTED,
        'linked_at' => now(),
    ]);

    $telegram = Mockery::mock(TelegramMtproto::class);
    $telegram->shouldReceive('resolvePeer')->once()->andReturn(202);
    $telegram->shouldReceive('sendDocument')
        ->once()
        ->withArgs(fn ($account, $peer, $path, $name, $mime, $caption) => $account->is($sender->telegramAccount) &&
            $peer === 202 &&
            str_ends_with($path, '.pdf') &&
            $name === 'silsilah.pdf' &&
            $mime === 'application/pdf' &&
            $caption === 'Dokumen keluarga')
        ->andReturn(['id' => 777]);
    app()->instance(TelegramMtproto::class, $telegram);

    $this->actingAs($sender)
        ->post(route('contacts.messages.store', $recipient), [
            'body' => 'Dokumen keluarga',
            'attachments' => [UploadedFile::fake()->create('silsilah.pdf', 10, 'application/pdf')],
        ])
        ->assertRedirect(route('contacts.show', $recipient));

    expect(Message::query()->firstOrFail()->telegram_message_id)->toBe(777);
});

test('message attachments are private and executable files are rejected', function () {
    Storage::fake('local');

    $marga = Marga::factory()->create();
    $sender = User::factory()->withMarga($marga->id)->create();
    $recipient = User::factory()->withMarga($marga->id)->create();
    $outsider = User::factory()->withMarga($marga->id)->create();

    $this->actingAs($sender)->post(route('contacts.messages.store', $recipient), [
        'attachments' => [UploadedFile::fake()->create('dokumen.pdf', 10, 'application/pdf')],
    ]);

    $attachment = MessageAttachment::query()->firstOrFail();

    $this->actingAs($outsider)
        ->get(route('message-attachments.show', $attachment))
        ->assertForbidden();

    $this->actingAs($sender)
        ->from(route('contacts.show', $recipient))
        ->post(route('contacts.messages.store', $recipient), [
            'attachments' => [UploadedFile::fake()->create('program.exe', 10, 'application/x-msdownload')],
        ])
        ->assertRedirect(route('contacts.show', $recipient))
        ->assertSessionHasErrors('attachments.0');

    expect(Message::query()->count())->toBe(1);
});

test('repeated messages reuse one conversation between the same contacts', function () {
    $marga = Marga::factory()->create();
    $firstUser = User::factory()->withMarga($marga->id)->create();
    $secondUser = User::factory()->withMarga($marga->id)->create();

    $this->actingAs($firstUser)
        ->post(route('contacts.messages.store', $secondUser), ['body' => 'Pesan pertama']);
    $this->actingAs($secondUser)
        ->post(route('contacts.messages.store', $firstUser), ['body' => 'Pesan kedua']);

    expect(Conversation::query()->count())->toBe(1)
        ->and(Message::query()->count())->toBe(2);
});

test('message text is required and limited to two thousand characters', function (string $body) {
    $marga = Marga::factory()->create();
    $sender = User::factory()->withMarga($marga->id)->create();
    $recipient = User::factory()->withMarga($marga->id)->create();

    $this->actingAs($sender)
        ->from(route('contacts.show', $recipient))
        ->post(route('contacts.messages.store', $recipient), ['body' => $body])
        ->assertRedirect(route('contacts.show', $recipient))
        ->assertSessionHasErrors('body');

    expect(Message::query()->count())->toBe(0);
})->with([
    'empty' => '',
    'whitespace' => '   ',
    'too long' => str_repeat('a', 2001),
]);

test('opening a conversation returns its messages and marks received messages as read', function () {
    $marga = Marga::factory()->create();
    $firstUser = User::factory()->withMarga($marga->id)->create();
    $secondUser = User::factory()->withMarga($marga->id)->create();
    $conversation = Conversation::query()->create(
        Conversation::participantAttributes($firstUser, $secondUser),
    );
    $received = $conversation->messages()->create([
        'sender_id' => $secondUser->id,
        'body' => 'Pesan belum dibaca',
    ]);

    $this->actingAs($firstUser)
        ->get(route('contacts.show', $secondUser))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('contacts/index')
            ->where('selectedContact.id', $secondUser->id)
            ->has('messages', 1)
            ->where('messages.0.body', 'Pesan belum dibaca')
            ->where('messages.0.is_mine', false));

    expect($received->fresh()->read_at)->not->toBeNull();
});

test('contact summaries include the latest message and unread count for that user', function () {
    $marga = Marga::factory()->create();
    $user = User::factory()->withMarga($marga->id)->create();
    $contact = User::factory()->withMarga($marga->id)->create();
    $conversation = Conversation::query()->create(
        Conversation::participantAttributes($user, $contact),
    );
    $conversation->messages()->createMany([
        ['sender_id' => $contact->id, 'body' => 'Sudah dibaca', 'read_at' => now()],
        ['sender_id' => $contact->id, 'body' => 'Pesan terbaru'],
    ]);

    $this->actingAs($user)
        ->get(route('contacts.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('contacts.0.latest_message', 'Pesan terbaru')
            ->where('contacts.0.unread_count', 1));
});

test('message events broadcast only to the recipient private channel', function () {
    $marga = Marga::factory()->create();
    $sender = User::factory()->withMarga($marga->id)->create();
    $recipient = User::factory()->withMarga($marga->id)->create();
    $conversation = Conversation::query()->create(
        Conversation::participantAttributes($sender, $recipient),
    );
    $message = $conversation->messages()->create([
        'sender_id' => $sender->id,
        'body' => 'Pesan privat',
    ]);

    $channels = (new MessageSent($message))->broadcastOn();

    expect($channels)->toHaveCount(1)
        ->and($channels[0])->toBeInstanceOf(PrivateChannel::class)
        ->and($channels[0]->name)->toBe('private-users.'.$recipient->id);
});

test('users can authorize only their own private message channel', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    config(['broadcasting.default' => 'reverb']);
    require base_path('routes/channels.php');

    $payload = ['socket_id' => '1234.5678'];

    $this->actingAs($user)
        ->postJson('/broadcasting/auth', [
            ...$payload,
            'channel_name' => 'private-users.'.$user->id,
        ])
        ->assertSuccessful();

    $this->actingAs($user)
        ->postJson('/broadcasting/auth', [
            ...$payload,
            'channel_name' => 'private-users.'.$otherUser->id,
        ])
        ->assertForbidden();
});

test('contacts include personName from currentPerson when available', function () {
    $marga = Marga::factory()->create();
    $user = User::factory()->withMarga($marga->id)->create();
    $person = Person::factory()->create(['marga_id' => $marga->id]);
    $user->update(['current_person_id' => $person->id]);

    $contact = User::factory()->withMarga($marga->id)->create();

    $this->actingAs($user)
        ->get(route('contacts.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('contacts', 1)
            ->where('contacts.0.name', $contact->name)
            ->where('contacts.0.personName', $person->name));
});

test('contacts have personName as null when user has no currentPerson', function () {
    $marga = Marga::factory()->create();
    $user = User::factory()->withMarga($marga->id)->create();
    $contact = User::factory()->withMarga($marga->id)->create();

    $this->actingAs($user)
        ->get(route('contacts.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('contacts', 1)
            ->where('contacts.0.personName', null));
});

test('contacts show Not Identified when currentPerson name is N/A', function () {
    $marga = Marga::factory()->create();
    $user = User::factory()->withMarga($marga->id)->create();
    $person = Person::factory()->create(['name' => 'N/A', 'marga_id' => $marga->id]);
    $user->update(['current_person_id' => $person->id]);

    $contact = User::factory()->withMarga($marga->id)->create();

    $this->actingAs($user)
        ->get(route('contacts.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('contacts', 1)
            ->where('contacts.0.personName', null));
});

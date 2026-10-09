<?php

use App\Models\Marga;
use App\Models\Person;
use App\Models\TaromboAiConversation;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

test('tanya ito uses the news Hermes connection and returns its answer as flash data', function () {
    config()->set('inertia.ssr.enabled', false);
    config()->set('services.hermes.base_url', 'https://hermes.test/v1');
    config()->set('services.hermes.token', 'shared-news-token');
    Http::preventStrayRequests();
    Http::fake([
        'hermes.test/v1/runs' => Http::response([
            'run_id' => 'ito-run',
            'status' => 'completed',
            'output' => 'Data silsilah belum cukup untuk menjawab.',
        ]),
    ]);
    $marga = Marga::factory()->create();
    $url = route('marga.ai.show', $marga);

    $this->actingAs(User::factory()->asAdmin()->create())
        ->from($url)
        ->post(route('marga.ai.ask', $marga), ['question' => 'Siapa leluhur marga ini?'])
        ->assertRedirect($url)
        ->assertInertiaFlash('tarombo_answer', 'Data silsilah belum cukup untuk menjawab.');

    Http::assertSent(function (Request $request) use ($marga) {
        $input = json_decode($request['input'], true);

        return $request->url() === 'https://hermes.test/v1/runs'
            && $request->hasHeader('Authorization', 'Bearer shared-news-token')
            && $input['question'] === 'Siapa leluhur marga ini?'
            && $input['marga']['name'] === $marga->name
            && isset($input['tree_rows'])
            && ! isset($input['instructions'])
            && str_contains($request['instructions'], 'Ito Tarombo');
    });

    $this->get($url)->assertSuccessful()->assertInertia(
        fn ($page) => $page->component('marga/tanya-tarombo')
            ->hasFlash('tarombo_answer', 'Data silsilah belum cukup untuk menjawab.')
    );
});

test('tanya ito returns a visible error when Hermes cannot connect', function () {
    config()->set('inertia.ssr.enabled', false);
    config()->set('services.hermes.base_url', 'https://hermes.test/v1');
    Http::preventStrayRequests();
    Http::fake(['hermes.test/*' => Http::failedConnection()]);
    $marga = Marga::factory()->create();

    $this->actingAs(User::factory()->asAdmin()->create())
        ->from(route('marga.ai.show', $marga))
        ->post(route('marga.ai.ask', $marga), ['question' => 'Siapa leluhur marga ini?'])
        ->assertRedirect()
        ->assertInertiaFlash('tarombo_error', 'Ito Tarombo sedang tidak tersedia. Coba lagi sebentar.');

    $this->get(route('marga.ai.show', $marga))->assertInertia(fn ($page) => $page
        ->has('messages', 2)
        ->where('messages.1.text', 'Ito Tarombo sedang tidak tersedia. Coba lagi sebentar.'));
});

function fakePersistedTaromboChat(): void
{
    config()->set('inertia.ssr.enabled', false);
    config()->set('services.hermes.base_url', 'https://hermes.test/v1');
    Http::preventStrayRequests();
    Http::fake(['hermes.test/v1/runs' => Http::response(['run_id' => 'chat-test', 'status' => 'completed', 'output' => 'Jawaban tersimpan.'])]);
}

test('marga chat survives reload and supplies previous messages for follow-up questions', function () {
    fakePersistedTaromboChat();
    $marga = Marga::factory()->create();
    $user = User::factory()->asAdmin()->create();
    $url = route('marga.ai.show', $marga);
    $this->actingAs($user)->from($url)->post(route('marga.ai.ask', $marga), ['question' => 'Pertanyaan pertama'])->assertRedirect($url);
    $conversation = TaromboAiConversation::query()->sole();
    $this->get($url)->assertInertia(fn ($page) => $page
        ->where('conversationId', $conversation->id)->has('messages', 2)
        ->where('messages.0.text', 'Pertanyaan pertama')->where('messages.1.text', 'Jawaban tersimpan.'));
    $this->from($url)->post(route('marga.ai.ask', $marga), ['question' => 'Jelaskan lanjutannya', 'conversation_id' => $conversation->id])->assertRedirect($url);
    Http::assertSent(fn (Request $request) => json_decode($request['input'], true)['question'] === 'Jelaskan lanjutannya'
        && count(json_decode($request['input'], true)['conversation_history']) === 2);
    expect($conversation->messages()->count())->toBe(4);
});

test('new chat starts empty while the previous conversation can be reopened', function () {
    fakePersistedTaromboChat();
    $marga = Marga::factory()->create();
    $this->actingAs(User::factory()->asAdmin()->create())->from(route('marga.ai.show', $marga))
        ->post(route('marga.ai.ask', $marga), ['question' => 'Riwayat lama']);
    $old = TaromboAiConversation::query()->sole();
    $this->post(route('marga.ai.new-conversation', $marga))->assertRedirect(route('marga.ai.show', $marga));
    $this->get(route('marga.ai.show', $marga))->assertInertia(fn ($page) => $page->has('messages', 0)->has('conversations', 2));
    $this->get(route('marga.ai.show', [$marga, 'conversation' => $old->id]))->assertInertia(fn ($page) => $page->has('messages', 2));
});

test('chat history is private to each user and cannot be posted to another marga scope', function () {
    fakePersistedTaromboChat();
    $marga = Marga::factory()->create();
    $otherMarga = Marga::factory()->create();
    $this->actingAs(User::factory()->asAdmin()->create())->from(route('marga.ai.show', $marga))
        ->post(route('marga.ai.ask', $marga), ['question' => 'Pesan pribadi']);
    $private = TaromboAiConversation::query()->sole();
    $this->postJson(route('marga.ai.ask', $otherMarga), ['question' => 'Salah scope', 'conversation_id' => $private->id])->assertNotFound();
    $this->actingAs(User::factory()->asAdmin()->create())->get(route('marga.ai.show', $marga))
        ->assertInertia(fn ($page) => $page->has('messages', 0));
    $this->getJson(route('marga.ai.show', [$marga, 'conversation' => $private->id]))->assertNotFound();
    $this->postJson(route('marga.ai.ask', $marga), ['question' => 'Tidak boleh', 'conversation_id' => $private->id])->assertNotFound();
    expect($private->messages()->count())->toBe(2);
});

test('all tab opens chat directly and has separate history from marga chats', function () {
    fakePersistedTaromboChat();
    $marga = Marga::factory()->create();
    $this->actingAs(User::factory()->asAdmin()->create())->get(route('marga.ai.select'))
        ->assertInertia(fn ($page) => $page->component('marga/tanya-tarombo')->where('marga', null)->has('messages', 0)->has('margas', 1));
    $this->from(route('marga.ai.select'))->post(route('marga.ai.ask-all'), ['question' => 'Tentang semua marga'])->assertRedirect(route('marga.ai.select'));
    $this->get(route('marga.ai.select'))->assertInertia(fn ($page) => $page->has('messages', 2));
    $this->get(route('marga.ai.show', $marga))->assertInertia(fn ($page) => $page->has('messages', 0));
    $this->post(route('marga.ai.new-general-conversation'))->assertRedirect(route('marga.ai.select'));
    $this->get(route('marga.ai.select'))->assertInertia(fn ($page) => $page->has('messages', 0)->has('conversations', 2));
});

test('general chat only sends margas and people within contributor access', function () {
    fakePersistedTaromboChat();
    $allowed = Marga::factory()->create();
    $hidden = Marga::factory()->create();
    Person::factory()->create(['marga_id' => $allowed->id, 'name' => 'Anggota Diizinkan']);
    Person::factory()->create(['marga_id' => $hidden->id, 'name' => 'Anggota Rahasia']);
    $this->actingAs(User::factory()->asMainContributor()->withMarga($allowed->id)->create())
        ->get(route('marga.ai.select'))->assertInertia(fn ($page) => $page->has('margas', 1)->where('margas.0.id', $allowed->id));
    $this->from(route('marga.ai.select'))->post(route('marga.ai.ask-all'), ['question' => 'Siapa anggota?'])->assertRedirect();
    Http::assertSent(function (Request $request) use ($allowed) {
        $input = json_decode($request['input'], true);

        return count($input['margas']) === 1 && $input['margas'][0]['id'] === $allowed->id
            && count($input['tree_rows']) === 1 && $input['tree_rows'][0]['name'] === 'Anggota Diizinkan';
    });
    $this->get(route('marga.ai.show', $hidden))->assertForbidden();
});

test('regular accounts cannot open or start general AI conversations', function () {
    config()->set('inertia.ssr.enabled', false);
    Http::preventStrayRequests();
    $this->actingAs(User::factory()->create())->get(route('marga.ai.select'))->assertForbidden();
    $this->postJson(route('marga.ai.ask-all'), ['question' => 'Pertanyaan'])->assertForbidden();
    $this->postJson(route('marga.ai.new-general-conversation'))->assertForbidden();
    $this->assertDatabaseCount('tarombo_ai_conversations', 0);
});

test('AI receives parent names and margas even when the parents are outside the selected marga', function (bool $general) {
    fakePersistedTaromboChat();
    $marga = Marga::factory()->create();
    $fatherMarga = Marga::factory()->create();
    $motherMarga = Marga::factory()->create();
    $father = Person::factory()->create(['name' => 'Ayah Di Luar Konteks', 'marga_id' => $fatherMarga->id]);
    $mother = Person::factory()->create(['name' => 'Ibu Di Luar Konteks', 'marga_id' => $motherMarga->id]);
    $child = Person::factory()->create(['name' => 'Anak Dalam Konteks', 'marga_id' => $marga->id, 'father_id' => $father->id, 'mother_id' => $mother->id]);
    $unrecorded = Person::factory()->create(['marga_id' => $marga->id, 'father_id' => null, 'mother_id' => null]);
    $actor = User::factory()->asMainContributor()->withMarga($marga->id)->create();

    $this->actingAs($actor)->post($general ? route('marga.ai.ask-all') : route('marga.ai.ask', $marga), ['question' => 'Siapa ayah dan ibunya?'])->assertRedirect();

    Http::assertSent(function (Request $request) use ($child, $unrecorded, $father, $mother, $fatherMarga, $motherMarga) {
        $rows = collect(json_decode($request['input'], true)['tree_rows'])->keyBy('id');
        $row = $rows[(string) $child->id];

        return count($rows) === 2
            && $row['parentId'] === (string) $father->id && $row['motherId'] === (string) $mother->id
            && $row['fatherName'] === $father->name && $row['fatherMarga'] === $fatherMarga->name
            && $row['motherName'] === $mother->name && $row['motherMarga'] === $motherMarga->name
            && $rows[(string) $unrecorded->id]['parentId'] === null
            && $rows[(string) $unrecorded->id]['fatherName'] === null
            && str_contains($request['instructions'], 'data ayah belum tercatat')
            && str_contains($request['instructions'], 'jangan hanya menampilkan ID');
    });
})->with([true, false]);

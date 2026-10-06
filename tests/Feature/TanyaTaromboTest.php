<?php

use App\Models\Marga;
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
});

<?php

use App\Models\Marga;
use App\Models\MargaDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

test('staff can upload and download a marga document', function () {
    Storage::fake('local');
    $admin = User::factory()->asAdmin()->create();
    $marga = Marga::factory()->create();

    $this->actingAs($admin)->post(route('marga.documents.store', $marga), [
        'title' => 'Sejarah Keluarga',
        'document' => UploadedFile::fake()->create('sejarah.pdf', 100, 'application/pdf'),
    ])->assertRedirect();

    $document = MargaDocument::firstOrFail();
    expect($document->title)->toBe('Sejarah Keluarga');
    Storage::disk('local')->assertExists($document->path);

    $this->actingAs($admin)->get(route('marga.documents.download', [$marga, $document]))
        ->assertSuccessful()
        ->assertHeader('Content-Disposition');
});

test('a regular user cannot access marga documents', function () {
    $marga = Marga::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('marga.documents.index', $marga))
        ->assertForbidden();
});

test('contributors only see and manage documents for assigned margas', function (string $role) {
    Storage::fake('local');
    $assigned = Marga::factory()->create();
    $family = Marga::factory()->create();
    $contributor = User::factory()->create(['role' => $role, 'marga_id' => $family->id]);
    $contributor->managedMargas()->attach($assigned);

    $this->actingAs($contributor)->get(route('marga.documents.select'))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('marga/select')
            ->has('margas', 1)
            ->where('margas.0.id', $assigned->id));

    $this->get(route('marga.documents.index', $assigned))->assertSuccessful();
    $this->get(route('marga.documents.index', $family))->assertForbidden();
    $this->post(route('marga.documents.store', $family), [
        'title' => 'Forbidden',
        'document' => UploadedFile::fake()->create('archive.pdf', 10, 'application/pdf'),
    ])->assertForbidden();

    $this->post(route('marga.documents.store', $assigned), [
        'title' => 'Assigned archive',
        'document' => UploadedFile::fake()->create('archive.pdf', 10, 'application/pdf'),
    ])->assertRedirect();

    $document = $assigned->documents()->firstOrFail();
    $this->get(route('marga.documents.download', [$assigned, $document]))->assertForbidden();
    $this->get(route('marga.documents.view', [$assigned, $document]))
        ->assertSuccessful()
        ->assertHeader('Content-Disposition', 'inline; filename=archive.pdf');
    $this->get(route('marga.documents.index', $assigned))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('canDownload', false)
            ->where('documents.0.download_url', null)
            ->where('documents.0.uploaded_by', $contributor->name)
            ->where('documents.0.created_at', $document->created_at->timezone('Asia/Jakarta')->format('d M Y, H:i').' WIB'));
    $this->get(route('marga.documents.view', [$family, $document]))->assertForbidden();
    $contributor->managedMargas()->detach($assigned);
    $this->get(route('marga.documents.view', [$assigned, $document]))->assertForbidden();
    $this->get(route('marga.documents.download', [$assigned, $document]))->assertForbidden();
    $this->delete(route('marga.documents.destroy', [$assigned, $document]))->assertForbidden();
    expect($document->fresh())->not->toBeNull();
})->with(['contributor_main', 'contributor_member']);

test('regular users cannot open the document selector', function () {
    $this->actingAs(User::factory()->create(['role' => 'user']))
        ->get(route('marga.documents.select'))->assertForbidden();
});

test('contributors without assignments see an empty document selector', function () {
    $user = User::factory()->create(['role' => 'contributor_main']);
    Marga::factory()->create();

    $this->actingAs($user)->get(route('marga.documents.select'))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('margas', 0));
});

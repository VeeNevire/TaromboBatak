<?php

use App\Models\Marga;
use App\Models\MargaDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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

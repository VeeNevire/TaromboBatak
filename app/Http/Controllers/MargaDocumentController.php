<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMargaDocumentRequest;
use App\Models\Marga;
use App\Models\MargaDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class MargaDocumentController extends Controller
{
    public function select(): \Inertia\Response
    {
        abort_unless(request()->user()?->isStaff(), 403);

        return Inertia::render('marga/select', [
            'feature' => 'documents',
            'margas' => Marga::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function index(Marga $marga): InertiaResponse
    {
        $this->authorizeAccess($marga);

        return Inertia::render('marga/documents', [
            'marga' => $marga->only(['id', 'name', 'color']),
            'documents' => $marga->documents()->with('uploader:id,name')->latest()->get()->map(fn (MargaDocument $document) => [
                'id' => $document->id,
                'title' => $document->title,
                'original_name' => $document->original_name,
                'mime_type' => $document->mime_type,
                'size_bytes' => $document->size_bytes,
                'uploaded_by' => $document->uploader?->name ?? 'Sistem',
                'created_at' => $document->created_at?->format('d M Y H:i'),
                'download_url' => route('marga.documents.download', [$marga, $document]),
            ]),
        ]);
    }

    public function store(StoreMargaDocumentRequest $request, Marga $marga): RedirectResponse
    {
        $this->authorizeAccess($marga);
        $file = $request->file('document');
        $path = $file->store('marga-documents/'.$marga->id, 'local');

        $marga->documents()->create([
            'uploaded_by' => $request->user()->id,
            'title' => $request->validated('title'),
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize() ?: 0,
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => 'Dokumen berhasil diunggah.']);
    }

    public function download(Marga $marga, MargaDocument $document): Response
    {
        $this->authorizeAccess($marga);
        abort_unless($document->marga_id === $marga->id && Storage::disk('local')->exists($document->path), 404);

        return Storage::disk('local')->response($document->path, $document->original_name, [
            'Content-Type' => $document->mime_type ?? 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="'.addslashes($document->original_name).'"',
        ]);
    }

    public function destroy(Marga $marga, MargaDocument $document): RedirectResponse
    {
        $this->authorizeAccess($marga);
        abort_unless($document->marga_id === $marga->id, 404);
        Storage::disk('local')->delete($document->path);
        $document->delete();

        return back()->with('toast', ['type' => 'success', 'message' => 'Dokumen dihapus.']);
    }

    private function authorizeAccess(Marga $marga): void
    {
        $user = request()->user();
        abort_unless($user?->isStaff() || $user?->isContributorOf($marga->id), 403);
    }
}

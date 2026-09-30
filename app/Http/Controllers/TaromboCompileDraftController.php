<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveTaromboCompileDraftRequest;
use App\Models\TaromboCompileDraft;
use App\Models\TaromboSnapshot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Saves the Compile Gambar arrangements of the signed-in account so they can
 * be opened and edited again. An account may keep several named saved
 * compiles of one snapshot (made with "Duplikat").
 */
class TaromboCompileDraftController extends Controller
{
    public function update(SaveTaromboCompileDraftRequest $request, TaromboSnapshot $taromboSnapshot): RedirectResponse
    {
        Gate::authorize('view', $taromboSnapshot);

        $userId = $request->user()->id;
        $disk = Storage::disk('local');
        /** @var array<string, mixed> $state */
        $state = $request->validated('state');
        $uploads = [];

        // Without an id the save starts a new saved compile.
        $draft = $request->filled('draft_id')
            ? TaromboCompileDraft::query()
                ->where('user_id', $userId)
                ->where('tarombo_snapshot_id', $taromboSnapshot->id)
                ->findOrFail($request->integer('draft_id'))
            : new TaromboCompileDraft(['user_id' => $userId, 'tarombo_snapshot_id' => $taromboSnapshot->id]);

        // New layer images get a uuid; the state refers to them from now on.
        $state['layers'] = collect($state['layers'] ?? [])
            ->map(function (array $layer) use ($request, $disk, $userId, $taromboSnapshot, &$uploads) {
                if (! str_starts_with($layer['source'], 'upload:')) {
                    return $layer;
                }

                $index = substr($layer['source'], 7);

                if (! isset($uploads[$index])) {
                    /** @var UploadedFile $file */
                    $file = $request->file("images.{$index}");
                    $uuid = (string) Str::uuid();
                    $disk->putFileAs(
                        TaromboCompileDraft::directory($userId, $taromboSnapshot->id),
                        $file,
                        "{$uuid}.png",
                    );
                    $uploads[$index] = $uuid;
                }

                return [...$layer, 'source' => 'stored:'.$uploads[$index]];
            })
            ->values()
            ->all();

        // A "stored:" image must belong to this account's saved compiles.
        foreach (TaromboCompileDraft::storedImages($state) as $uuid) {
            abort_unless(
                $disk->exists(TaromboCompileDraft::imagePath($userId, $taromboSnapshot->id, $uuid)),
                422,
                'Gambar lapisan tidak ditemukan. Muat ulang halaman lalu simpan lagi.',
            );
        }

        $draft->fill(['tarombo_frame_id' => $state['frame_id'] ?? null, 'state' => $state])->save();

        $this->deleteUnusedImages($userId, $taromboSnapshot->id);

        // Without a frame there is nothing Produce-like to show.
        $preview = $request->file('preview');

        if ($preview instanceof UploadedFile && $draft->tarombo_frame_id !== null) {
            $disk->putFileAs(dirname($draft->previewPath()), $preview, basename($draft->previewPath()));
        } elseif ($draft->tarombo_frame_id === null) {
            $disk->delete($draft->previewPath());
        }

        return back()->with('toast', ['type' => 'success', 'message' => 'Compile Gambar disimpan. Bisa dibuka dan diedit lagi nanti.']);
    }

    public function rename(Request $request, TaromboCompileDraft $taromboCompileDraft): RedirectResponse
    {
        $this->authorizeOwner($request, $taromboCompileDraft);

        $validated = $request->validate(['name' => ['required', 'string', 'max:120']]);

        $taromboCompileDraft->update(['name' => trim($validated['name'])]);

        return back()->with('toast', ['type' => 'success', 'message' => 'Nama gambar diperbarui.']);
    }

    /** A copy with its own name; layer images stay shared with the original. */
    public function duplicate(Request $request, TaromboCompileDraft $taromboCompileDraft): RedirectResponse
    {
        $this->authorizeOwner($request, $taromboCompileDraft);

        $validated = $request->validate(['name' => ['required', 'string', 'max:120']]);

        $copy = $taromboCompileDraft->replicate();
        $copy->name = trim($validated['name']);
        $copy->save();

        $disk = Storage::disk('local');

        if ($disk->exists($taromboCompileDraft->previewPath())) {
            $disk->copy($taromboCompileDraft->previewPath(), $copy->previewPath());
        }

        return back()->with('toast', ['type' => 'success', 'message' => 'Gambar berhasil diduplikat.']);
    }

    public function destroy(Request $request, TaromboCompileDraft $taromboCompileDraft): RedirectResponse
    {
        $this->authorizeOwner($request, $taromboCompileDraft);

        Storage::disk('local')->delete($taromboCompileDraft->previewPath());
        $taromboCompileDraft->delete();
        $this->deleteUnusedImages($taromboCompileDraft->user_id, $taromboCompileDraft->tarombo_snapshot_id);

        return back()->with('toast', ['type' => 'success', 'message' => 'Simpanan Compile Gambar dihapus.']);
    }

    /** A layer image of the signed-in account's own saved compiles. */
    public function image(Request $request, TaromboSnapshot $taromboSnapshot, string $uuid): StreamedResponse
    {
        Gate::authorize('view', $taromboSnapshot);
        abort_unless(Str::isUuid($uuid), 404);

        $path = TaromboCompileDraft::imagePath($request->user()->id, $taromboSnapshot->id, $uuid);

        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, 'lapisan.png', [
            'Cache-Control' => 'private, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }

    /** The composed preview of one of the signed-in account's saved compiles. */
    public function preview(Request $request, TaromboCompileDraft $taromboCompileDraft): StreamedResponse
    {
        $this->authorizeOwner($request, $taromboCompileDraft);

        abort_unless(Storage::disk('local')->exists($taromboCompileDraft->previewPath()), 404);

        return Storage::disk('local')->response($taromboCompileDraft->previewPath(), 'hasil-simpan.jpg', [
            'Cache-Control' => 'private, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }

    private function authorizeOwner(Request $request, TaromboCompileDraft $draft): void
    {
        abort_unless($draft->user_id === $request->user()->id, 404);
    }

    /** Removes layer images no saved compile of this account and snapshot uses. */
    private function deleteUnusedImages(int $userId, int $snapshotId): void
    {
        $disk = Storage::disk('local');
        $keep = TaromboCompileDraft::usedImages($userId, $snapshotId);

        foreach ($disk->files(TaromboCompileDraft::directory($userId, $snapshotId)) as $file) {
            if (str_starts_with(basename($file), TaromboCompileDraft::PREVIEW_PREFIX)) {
                continue;
            }

            if (! in_array(pathinfo($file, PATHINFO_FILENAME), $keep, true)) {
                $disk->delete($file);
            }
        }
    }
}

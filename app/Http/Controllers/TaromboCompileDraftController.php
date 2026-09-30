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
 * Saves the Compile Gambar arrangement of the signed-in account so it can be
 * opened and edited again. One saved arrangement per account and snapshot.
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

        // A "stored:" image must belong to this account's draft.
        foreach (TaromboCompileDraft::storedImages($state) as $uuid) {
            abort_unless(
                $disk->exists(TaromboCompileDraft::imagePath($userId, $taromboSnapshot->id, $uuid)),
                422,
                'Gambar lapisan tidak ditemukan. Muat ulang halaman lalu simpan lagi.',
            );
        }

        TaromboCompileDraft::query()->updateOrCreate(
            ['user_id' => $userId, 'tarombo_snapshot_id' => $taromboSnapshot->id],
            ['tarombo_frame_id' => $state['frame_id'] ?? null, 'state' => $state],
        );

        $this->deleteUnusedImages($userId, $taromboSnapshot->id, TaromboCompileDraft::storedImages($state));

        // Without a frame there is nothing Produce-like to show.
        $previewPath = TaromboCompileDraft::previewPath($userId, $taromboSnapshot->id);
        $preview = $request->file('preview');

        if ($preview instanceof UploadedFile && ($state['frame_id'] ?? null) !== null) {
            $disk->putFileAs(dirname($previewPath), $preview, basename($previewPath));
        } elseif (($state['frame_id'] ?? null) === null) {
            $disk->delete($previewPath);
        }

        return back()->with('toast', ['type' => 'success', 'message' => 'Compile Gambar disimpan. Bisa dibuka dan diedit lagi nanti.']);
    }

    public function destroy(Request $request, TaromboSnapshot $taromboSnapshot): RedirectResponse
    {
        Gate::authorize('view', $taromboSnapshot);

        $userId = $request->user()->id;

        TaromboCompileDraft::query()
            ->where('user_id', $userId)
            ->where('tarombo_snapshot_id', $taromboSnapshot->id)
            ->delete();
        Storage::disk('local')->deleteDirectory(TaromboCompileDraft::directory($userId, $taromboSnapshot->id));

        return back()->with('toast', ['type' => 'success', 'message' => 'Simpanan Compile Gambar dihapus.']);
    }

    /** A layer image of the signed-in account's own draft. */
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

    /** The composed preview of the signed-in account's own draft. */
    public function preview(Request $request, TaromboSnapshot $taromboSnapshot): StreamedResponse
    {
        Gate::authorize('view', $taromboSnapshot);

        $path = TaromboCompileDraft::previewPath($request->user()->id, $taromboSnapshot->id);

        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, 'hasil-simpan.jpg', [
            'Cache-Control' => 'private, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }

    /** @param  array<int, string>  $keep */
    private function deleteUnusedImages(int $userId, int $snapshotId, array $keep): void
    {
        $disk = Storage::disk('local');

        foreach ($disk->files(TaromboCompileDraft::directory($userId, $snapshotId)) as $file) {
            if (basename($file) === TaromboCompileDraft::PREVIEW_FILE) {
                continue;
            }

            if (! in_array(pathinfo($file, PATHINFO_FILENAME), $keep, true)) {
                $disk->delete($file);
            }
        }
    }
}

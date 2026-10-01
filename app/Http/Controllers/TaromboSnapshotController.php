<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenerateTaromboFrameRequest;
use App\Http\Requests\StoreTaromboSnapshotRequest;
use App\Models\TaromboCompileDraft;
use App\Models\TaromboFrame;
use App\Models\TaromboSnapshot;
use App\Services\FamilyTreeActivityLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaromboSnapshotController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', TaromboSnapshot::class);

        $user = $request->user();
        $canDownload = $user->isStaff();
        $ownerScope = fn ($query) => $query->when(
            ! $user->isStaff(),
            fn ($scoped) => $scoped->whereBelongsTo($user),
        );

        // Snapshots with a saved Compile Gambar arrangement of this account.
        $draftSnapshotIds = TaromboCompileDraft::query()
            ->where('user_id', $user->id)
            ->whereNotNull('tarombo_snapshot_id')
            ->pluck('tarombo_snapshot_id')
            ->flip();

        $filter = $request->string('filter')->toString();
        $filter = in_array($filter, ['compiled', 'original', 'free', 'saved'], true) ? $filter : 'all';

        $snapshots = $filter === 'saved'
            ? $this->savedCompiles($user->id)
            : TaromboSnapshot::query()
                ->tap($ownerScope)
                ->when($filter === 'compiled', fn ($query) => $query->whereNotNull('tarombo_frame_id'))
                ->when($filter === 'original', fn ($query) => $query->whereNull('tarombo_frame_id'))
                // Free originals: raw tree images with no saved compile arrangement.
                ->when($filter === 'free', fn ($query) => $query
                    ->whereNull('tarombo_frame_id')
                    ->whereDoesntHave('compileDrafts'))
                ->with(['centerPerson:id,name', 'user:id,name'])
                ->latest()
                ->paginate(12)
                ->withQueryString()
                ->through(fn (TaromboSnapshot $snapshot) => [
                    'id' => $snapshot->id,
                    'view' => $snapshot->view,
                    'title' => $snapshot->title,
                    'center_person_name' => $snapshot->centerPerson?->name,
                    'owner_name' => $snapshot->user?->name,
                    'image_url' => route('tarombo.snapshots.image', $snapshot),
                    'download_url' => $canDownload
                        ? route('tarombo.snapshots.download', $snapshot)
                        : null,
                    'can_delete' => $snapshot->user_id === $user->id,
                    'size_bytes' => Storage::disk('local')->exists($snapshot->path)
                        ? Storage::disk('local')->size($snapshot->path)
                        : null,
                    'created_at' => $snapshot->created_at?->toISOString(),
                    // A result shares the saved arrangement of the tree it came from.
                    'has_compile_draft' => $draftSnapshotIds->has($snapshot->source_snapshot_id ?? $snapshot->id),
                    'editable_result' => $snapshot->source_snapshot_id !== null && $snapshot->user_id === $user->id,
                ]);

        $snapshotOptions = TaromboSnapshot::query()
            ->tap($ownerScope)
            ->with(['centerPerson:id,name', 'user:id,name'])
            ->latest()
            ->limit(60)
            ->get()
            ->map(fn (TaromboSnapshot $snapshot) => [
                ...$this->snapshotData($snapshot),
                'has_compile_draft' => $draftSnapshotIds->has($snapshot->source_snapshot_id ?? $snapshot->id),
            ]);

        return Inertia::render('tarombo/snapshots', [
            'snapshots' => $snapshots,
            'filter' => $filter,
            'snapshotOptions' => $snapshotOptions,
            'frames' => TaromboFrame::query()
                ->active()
                ->nonCollage()
                ->latest()
                ->get()
                ->map(fn (TaromboFrame $frame) => [
                    'id' => $frame->id,
                    'name' => $frame->name,
                    'image_url' => route('tarombo-frames.image', $frame),
                ]),
            'accountName' => $user->name,
            'canDownload' => $canDownload,
        ]);
    }

    public function compile(Request $request, TaromboSnapshot $taromboSnapshot): Response
    {
        Gate::authorize('view', $taromboSnapshot);

        // Opening a produced result edits the tree it came from, and Produce
        // then replaces that result instead of adding another image.
        // Results without a source (older ones) are compiled as plain images.
        $target = null;
        $source = $taromboSnapshot->source;

        if ($source !== null) {
            Gate::authorize('view', $source);
            // Only the owner may replace the result; others produce a new image.
            $target = $request->user()->can('delete', $taromboSnapshot) ? $taromboSnapshot : null;
            $taromboSnapshot = $source;
        }

        return $this->compilePage($request, $taromboSnapshot, $target);
    }

    /** Compile Gambar on a blank canvas: only a frame, ranting and background layers. */
    public function compileBlank(Request $request): Response
    {
        return $this->compilePage($request, null, null);
    }

    private function compilePage(Request $request, ?TaromboSnapshot $snapshot, ?TaromboSnapshot $target): Response
    {
        return Inertia::render('tarombo/snapshot-compile', [
            'snapshot' => $snapshot === null ? null : [
                'id' => $snapshot->id,
                'view' => $snapshot->view,
                'title' => $snapshot->title,
                'center_person_name' => $snapshot->centerPerson?->name,
                'image_url' => route('tarombo.snapshots.image', $snapshot),
            ],
            'targetSnapshotId' => $target?->id,
            'frames' => TaromboFrame::query()
                ->active()
                ->nonCollage()
                ->latest()
                ->get()
                ->map(fn (TaromboFrame $frame) => [
                    'id' => $frame->id,
                    'name' => $frame->name,
                    'image_url' => route('tarombo-frames.image', $frame),
                    'canvas_width' => $frame->canvas_width,
                    'canvas_height' => $frame->canvas_height,
                    'area_x' => $frame->area_x,
                    'area_y' => $frame->area_y,
                    'area_width' => $frame->area_width,
                    'area_height' => $frame->area_height,
                ]),
            // Ranting layers are picked from the free originals (no saved compile).
            'freeOriginals' => TaromboSnapshot::query()
                ->when(
                    ! $request->user()->isStaff(),
                    fn ($query) => $query->whereBelongsTo($request->user()),
                )
                ->whereNull('tarombo_frame_id')
                ->whereDoesntHave('compileDrafts')
                ->when($snapshot !== null, fn ($query) => $query->whereKeyNot($snapshot->id))
                ->with('centerPerson:id,name')
                ->latest()
                ->limit(60)
                ->get()
                ->map(fn (TaromboSnapshot $free) => [
                    'id' => $free->id,
                    'title' => $free->title,
                    'center_person_name' => $free->centerPerson?->name,
                    'image_url' => route('tarombo.snapshots.image', $free),
                ]),
            'accountName' => $request->user()->name,
            'draft' => $this->draftData($request->user()->id, $snapshot, $request->integer('draft') ?: null),
        ]);
    }

    /**
     * The signed-in account's saved Compile Gambar arrangement, with a URL for
     * every layer image it uses: the one asked for (?draft=), otherwise the
     * most recently saved one.
     *
     * @return array<string, mixed>|null
     */
    private function draftData(int $userId, ?TaromboSnapshot $snapshot, ?int $draftId): ?array
    {
        $drafts = TaromboCompileDraft::query()
            ->where('user_id', $userId)
            ->where('tarombo_snapshot_id', $snapshot?->id);

        // A blank canvas starts empty unless a saved compile is asked for.
        $draft = ($draftId !== null ? (clone $drafts)->find($draftId) : null)
            ?? ($snapshot !== null ? $drafts->latest('updated_at')->latest('id')->first() : null);

        if ($draft === null) {
            return null;
        }

        return [
            'id' => $draft->id,
            'name' => $draft->name,
            'state' => $draft->state,
            'image_urls' => collect(TaromboCompileDraft::storedImages($draft->state))
                ->mapWithKeys(fn (string $uuid) => [
                    $uuid => $snapshot === null
                        ? route('tarombo.compile.blank.draft.image', $uuid)
                        : route('tarombo.snapshots.compile.draft.image', [$snapshot, $uuid]),
                ]),
            'updated_at' => $draft->updated_at?->toIso8601String(),
        ];
    }

    public function store(StoreTaromboSnapshotRequest $request): RedirectResponse
    {
        Gate::authorize('create', TaromboSnapshot::class);

        $image = $request->file('image');

        abort_unless($image instanceof UploadedFile, 422);

        $path = $image->store('tarombo-snapshots/'.$request->user()->id, 'local');

        abort_if($path === false, 500, 'Gambar pohon gagal disimpan.');

        // The save dialog (title, resolution, paper, branches) is reserved for
        // admin and sub-admin accounts; everyone else saves the tree as shown.
        $isStaff = $request->user()->isStaff();

        $request->user()->taromboSnapshots()->create([
            'center_person_id' => $request->validated('center_person_id'),
            'view' => $request->validated('view'),
            'title' => $isStaff ? $request->validated('title') : null,
            'resolution' => $isStaff ? $request->validated('resolution') : null,
            'paper_size' => $isStaff ? $request->validated('paper_size') : null,
            'orientation' => $isStaff ? $request->validated('orientation') : null,
            'included_person_ids' => $isStaff ? $request->validated('included_person_ids') : null,
            'path' => $path,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Pohon berhasil disimpan ke akun.',
        ]);

        return back();
    }

    /**
     * Stores the tree-in-frame image compiled in the browser on the compile page.
     */
    public function generate(GenerateTaromboFrameRequest $request): RedirectResponse
    {
        // No snapshot: the compile was made on a blank canvas.
        $snapshot = $request->filled('snapshot_id')
            ? TaromboSnapshot::query()
                ->when(
                    ! $request->user()->isStaff(),
                    fn ($query) => $query->whereBelongsTo($request->user()),
                )
                ->findOrFail($request->integer('snapshot_id'))
            : null;
        $frame = TaromboFrame::query()
            ->active()
            ->findOrFail($request->integer('frame_id'));

        $target = null;

        if ($request->filled('target_snapshot_id')) {
            $target = TaromboSnapshot::query()->findOrFail($request->integer('target_snapshot_id'));

            Gate::authorize('delete', $target);
            abort_unless($snapshot !== null && $target->source_snapshot_id === $snapshot->id, 422, 'Gambar hasil tidak berasal dari gambar ini.');
        }

        $image = $request->file('image');

        abort_unless($image instanceof UploadedFile, 422);

        $path = $image->store('tarombo-snapshots/'.$request->user()->id, 'local');

        abort_if($path === false, 500, 'Gambar gabungan gagal disimpan.');

        if ($target !== null) {
            $oldPath = $target->path;

            $target->update([
                'tarombo_frame_id' => $frame->id,
                'path' => $path,
            ]);

            Storage::disk('local')->delete($oldPath);
        } else {
            $request->user()->taromboSnapshots()->create([
                'center_person_id' => $snapshot?->center_person_id,
                'tarombo_frame_id' => $frame->id,
                'source_snapshot_id' => $snapshot?->id,
                'view' => $snapshot->view ?? 'tree',
                'path' => $path,
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $target !== null
                ? 'Gambar hasil compile berhasil diperbarui.'
                : 'Gambar Tarombo berhasil digabungkan dengan frame.',
        ]);

        return to_route('tarombo.snapshots.index');
    }

    public function image(TaromboSnapshot $taromboSnapshot): StreamedResponse
    {
        Gate::authorize('view', $taromboSnapshot);
        abort_unless(Storage::disk('local')->exists($taromboSnapshot->path), 404);

        $extension = pathinfo($taromboSnapshot->path, PATHINFO_EXTENSION) ?: 'jpg';

        return Storage::disk('local')->response(
            $taromboSnapshot->path,
            "pohon-tarombo.{$extension}",
            [
                'Cache-Control' => 'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ],
            'inline',
        );
    }

    public function download(Request $request, TaromboSnapshot $taromboSnapshot): StreamedResponse
    {
        Gate::authorize('download', $taromboSnapshot);
        abort_unless(Storage::disk('local')->exists($taromboSnapshot->path), 404);

        $taromboSnapshot->loadMissing(['centerPerson:id,name', 'user:id,name']);

        app(FamilyTreeActivityLogger::class)->logSnapshotDownload($taromboSnapshot, $request->user());

        $name = $taromboSnapshot->title
            ?: $taromboSnapshot->centerPerson?->name
            ?: 'pohon-tarombo';
        $extension = pathinfo($taromboSnapshot->path, PATHINFO_EXTENSION) ?: 'jpg';

        return Storage::disk('local')->download(
            $taromboSnapshot->path,
            Str::slug($name).'-'.now()->format('Ymd-His').".{$extension}",
            [
                'Cache-Control' => 'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    public function destroy(TaromboSnapshot $taromboSnapshot): RedirectResponse
    {
        Gate::authorize('delete', $taromboSnapshot);

        Storage::disk('local')->delete($taromboSnapshot->path);

        // Saved Compile Gambar arrangements go with their snapshot.
        foreach (TaromboCompileDraft::query()->where('tarombo_snapshot_id', $taromboSnapshot->id)->pluck('user_id') as $userId) {
            Storage::disk('local')->deleteDirectory(TaromboCompileDraft::directory($userId, $taromboSnapshot->id));
        }

        $taromboSnapshot->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Gambar Tarombo berhasil dihapus.',
        ]);

        return back();
    }

    /**
     * The "Hasil Simpan" tab: this account's saved Compile Gambar arrangements,
     * shown with the composed preview made on save (the source tree image for
     * arrangements saved before previews existed).
     *
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function savedCompiles(int $userId): LengthAwarePaginator
    {
        $disk = Storage::disk('local');

        return TaromboCompileDraft::query()
            ->where('user_id', $userId)
            ->with(['snapshot.centerPerson:id,name', 'snapshot.user:id,name', 'user:id,name'])
            ->latest('updated_at')
            ->latest('id')
            ->paginate(12)
            ->withQueryString()
            ->through(function (TaromboCompileDraft $draft) use ($disk) {
                $snapshot = $draft->snapshot;
                $previewPath = $draft->existingPreviewPath();
                $hasPreview = $previewPath !== null;

                return [
                    // 0 and blank_canvas for a compile made without a tree image.
                    'id' => $snapshot->id ?? 0,
                    'blank_canvas' => $snapshot === null,
                    'draft_id' => $draft->id,
                    'draft_name' => $draft->name,
                    'view' => $snapshot->view ?? 'tree',
                    // A saved compile is shown under its own name once it has one.
                    'title' => $draft->name ?? $snapshot?->title,
                    'center_person_name' => $snapshot?->centerPerson?->name,
                    'owner_name' => ($snapshot ?? $draft)->user?->name,
                    'image_url' => $hasPreview || $snapshot === null
                        ? route('tarombo.compile-drafts.preview', ['taromboCompileDraft' => $draft, 'v' => $draft->updated_at?->timestamp])
                        : route('tarombo.snapshots.image', $snapshot),
                    'download_url' => null,
                    'can_delete' => false,
                    'size_bytes' => $hasPreview ? $disk->size($previewPath) : null,
                    'created_at' => $draft->updated_at?->toISOString(),
                    'has_compile_draft' => true,
                    'editable_result' => false,
                    'saved_compile' => true,
                    'has_preview' => $hasPreview,
                ];
            });
    }

    /** @return array<string, mixed> */
    private function snapshotData(TaromboSnapshot $snapshot): array
    {
        return [
            'id' => $snapshot->id,
            'view' => $snapshot->view,
            'title' => $snapshot->title,
            'center_person_name' => $snapshot->centerPerson?->name,
            'owner_name' => $snapshot->user?->name,
            'image_url' => route('tarombo.snapshots.image', $snapshot),
            'size_bytes' => Storage::disk('local')->exists($snapshot->path)
                ? Storage::disk('local')->size($snapshot->path)
                : null,
            'created_at' => $snapshot->created_at?->toISOString(),
        ];
    }
}

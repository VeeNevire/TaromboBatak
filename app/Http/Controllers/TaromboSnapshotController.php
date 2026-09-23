<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenerateTaromboFrameRequest;
use App\Http\Requests\StoreTaromboSnapshotRequest;
use App\Http\Requests\UpdateTaromboAiPromptRequest;
use App\Models\TaromboAiPrompt;
use App\Models\TaromboFrame;
use App\Models\TaromboSnapshot;
use App\Services\FamilyTreeActivityLogger;
use App\Services\TaromboFrameComposer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaromboSnapshotController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', TaromboSnapshot::class);

        $user = $request->user();
        $canManageAiPrompt = $user->isAdmin();
        $canDownload = $user->isStaff();
        $ownerScope = fn ($query) => $query->when(
            ! $user->isStaff(),
            fn ($scoped) => $scoped->whereBelongsTo($user),
        );

        $snapshots = TaromboSnapshot::query()
            ->tap($ownerScope)
            ->with(['centerPerson:id,name', 'user:id,name'])
            ->latest()
            ->paginate(12)
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
                'created_at' => $snapshot->created_at?->toISOString(),
            ]);

        $snapshotOptions = TaromboSnapshot::query()
            ->tap($ownerScope)
            ->with(['centerPerson:id,name', 'user:id,name'])
            ->latest()
            ->limit(60)
            ->get()
            ->map(fn (TaromboSnapshot $snapshot) => $this->snapshotData($snapshot));

        $activeFrames = TaromboFrame::query()
            ->active()
            ->latest()
            ->get()
            ->map(fn (TaromboFrame $frame) => [
                'id' => $frame->id,
                'name' => $frame->name,
                'image_url' => route('tarombo-frames.image', $frame),
            ]);

        return Inertia::render('tarombo/snapshots', [
            'snapshots' => $snapshots,
            'snapshotOptions' => $snapshotOptions,
            'activeFrames' => $activeFrames,
            'accountName' => $user->name,
            'canDownload' => $canDownload,
            'canManageAiPrompt' => $canManageAiPrompt,
            'aiPrompt' => $canManageAiPrompt
                ? TaromboAiPrompt::query()
                    ->where('key', TaromboAiPrompt::FRAME_COMPOSITION_KEY)
                    ->value('prompt') ?? TaromboAiPrompt::DEFAULT_FRAME_COMPOSITION
                : null,
        ]);
    }

    public function updatePrompt(UpdateTaromboAiPromptRequest $request): RedirectResponse
    {
        TaromboAiPrompt::query()->updateOrCreate(
            ['key' => TaromboAiPrompt::FRAME_COMPOSITION_KEY],
            ['prompt' => $request->validated('prompt')],
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Prompt Gen AI berhasil disimpan.',
        ]);

        return back();
    }

    public function store(StoreTaromboSnapshotRequest $request): RedirectResponse
    {
        Gate::authorize('create', TaromboSnapshot::class);

        $image = $request->file('image');

        abort_unless($image instanceof UploadedFile, 422);

        $path = $image->store('tarombo-snapshots/'.$request->user()->id, 'local');

        abort_if($path === false, 500, 'Gambar pohon gagal disimpan.');

        $request->user()->taromboSnapshots()->create([
            'center_person_id' => $request->validated('center_person_id'),
            'view' => $request->validated('view'),
            'title' => $request->validated('title'),
            'resolution' => $request->validated('resolution'),
            'paper_size' => $request->validated('paper_size'),
            'included_person_ids' => $request->validated('included_person_ids'),
            'path' => $path,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Pohon berhasil disimpan ke akun.',
        ]);

        return back();
    }

    public function generate(
        GenerateTaromboFrameRequest $request,
        TaromboFrameComposer $composer,
    ): RedirectResponse {
        $snapshot = TaromboSnapshot::query()
            ->when(
                ! $request->user()->isStaff(),
                fn ($query) => $query->whereBelongsTo($request->user()),
            )
            ->findOrFail($request->integer('snapshot_id'));
        $frame = TaromboFrame::query()
            ->active()
            ->findOrFail($request->integer('frame_id'));

        abort_unless(Storage::disk('local')->exists($snapshot->path), 422, 'Gambar Tarombo sumber tidak tersedia.');
        abort_unless(Storage::disk('local')->exists($frame->path), 422, 'Template frame tidak tersedia.');

        try {
            $path = $composer->compose($snapshot, $frame);
        } catch (\RuntimeException $exception) {
            throw ValidationException::withMessages([
                'frame_id' => $exception->getMessage(),
            ]);
        }

        $request->user()->taromboSnapshots()->create([
            'center_person_id' => $snapshot->center_person_id,
            'tarombo_frame_id' => $frame->id,
            'view' => $snapshot->view,
            'path' => $path,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Gambar Tarombo dengan frame berhasil dibuat oleh AI.',
        ]);

        return back();
    }

    public function image(TaromboSnapshot $taromboSnapshot): StreamedResponse
    {
        Gate::authorize('view', $taromboSnapshot);
        abort_unless(Storage::disk('local')->exists($taromboSnapshot->path), 404);

        return Storage::disk('local')->response(
            $taromboSnapshot->path,
            'pohon-tarombo.jpg',
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

        return Storage::disk('local')->download(
            $taromboSnapshot->path,
            Str::slug($name).'-'.now()->format('Ymd-His').'.jpg',
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
        $taromboSnapshot->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Gambar Tarombo berhasil dihapus.',
        ]);

        return back();
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
            'created_at' => $snapshot->created_at?->toISOString(),
        ];
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenerateTaromboFrameRequest;
use App\Http\Requests\StoreTaromboSnapshotRequest;
use App\Models\TaromboFrame;
use App\Models\TaromboSnapshot;
use App\Services\TaromboFrameComposer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaromboSnapshotController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', TaromboSnapshot::class);

        $snapshots = TaromboSnapshot::query()
            ->whereBelongsTo($request->user())
            ->with('centerPerson:id,name')
            ->latest()
            ->paginate(12)
            ->through(fn (TaromboSnapshot $snapshot) => [
                'id' => $snapshot->id,
                'view' => $snapshot->view,
                'center_person_name' => $snapshot->centerPerson?->name,
                'image_url' => route('tarombo.snapshots.image', $snapshot),
                'created_at' => $snapshot->created_at?->toISOString(),
            ]);

        $snapshotOptions = TaromboSnapshot::query()
            ->whereBelongsTo($request->user())
            ->with('centerPerson:id,name')
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
            'accountName' => $request->user()->name,
        ]);
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
            ->whereBelongsTo($request->user())
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
            'center_person_name' => $snapshot->centerPerson?->name,
            'image_url' => route('tarombo.snapshots.image', $snapshot),
            'created_at' => $snapshot->created_at?->toISOString(),
        ];
    }
}

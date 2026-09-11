<?php

namespace App\Http\Controllers;

use App\Http\Requests\TaromboFrameRequest;
use App\Models\TaromboFrame;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaromboFrameController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('tarombo/frames', [
            'frames' => TaromboFrame::query()->latest()->get()->map(fn (TaromboFrame $frame) => $this->frameData($frame)),
        ]);
    }

    public function store(TaromboFrameRequest $request): RedirectResponse
    {
        /** @var UploadedFile $image */
        $image = $request->file('image');
        $path = $image->store('tarombo-frames', 'local');
        abort_if($path === false, 500, 'Frame gagal disimpan.');
        [$width, $height] = getimagesize(Storage::disk('local')->path($path));

        TaromboFrame::query()->create([
            ...$request->safe()->except('image'),
            'path' => $path,
            'canvas_width' => $width,
            'canvas_height' => $height,
            'area_x' => 0,
            'area_y' => 0,
            'area_width' => $width,
            'area_height' => $height,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => 'Template frame berhasil ditambahkan.']);
    }

    public function update(TaromboFrameRequest $request, TaromboFrame $taromboFrame): RedirectResponse
    {
        $data = $request->safe()->except('image');
        $data['is_active'] = $request->boolean('is_active', false);
        $oldPath = null;
        $image = $request->file('image');

        if ($image instanceof UploadedFile) {
            $path = $image->store('tarombo-frames', 'local');
            abort_if($path === false, 500, 'Frame gagal disimpan.');
            [$data['canvas_width'], $data['canvas_height']] = getimagesize(Storage::disk('local')->path($path));
            $data['path'] = $path;
            $oldPath = $taromboFrame->path;
        }

        $taromboFrame->update($data);

        if ($oldPath !== null) {
            Storage::disk('local')->delete($oldPath);
        }

        return back()->with('toast', ['type' => 'success', 'message' => 'Template frame berhasil diperbarui.']);
    }

    public function image(Request $request, TaromboFrame $taromboFrame): StreamedResponse
    {
        abort_unless($taromboFrame->is_active || $request->user()?->isAdmin(), 404);
        abort_unless(Storage::disk('local')->exists($taromboFrame->path), 404);

        return Storage::disk('local')->response($taromboFrame->path, 'frame-tarombo.jpg', [
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }

    public function destroy(TaromboFrame $taromboFrame): RedirectResponse
    {
        Storage::disk('local')->delete($taromboFrame->path);
        $taromboFrame->delete();

        return back()->with('toast', ['type' => 'success', 'message' => 'Template frame berhasil dihapus.']);
    }

    /** @return array<string, mixed> */
    private function frameData(TaromboFrame $frame): array
    {
        return [
            'id' => $frame->id,
            'name' => $frame->name,
            'image_url' => route('tarombo-frames.image', $frame),
            'canvas_width' => $frame->canvas_width,
            'canvas_height' => $frame->canvas_height,
            'area_x' => $frame->area_x,
            'area_y' => $frame->area_y,
            'area_width' => $frame->area_width,
            'area_height' => $frame->area_height,
            'is_active' => $frame->is_active,
        ];
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\TaromboFrameRequest;
use App\Http\Requests\UpdateTaromboFrameAreaRequest;
use App\Models\TaromboFrame;
use App\Services\TaromboFrameUpscaler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
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
            [$width, $height] = getimagesize(Storage::disk('local')->path($path));
            $data = [...$data, ...$this->scaledArea($taromboFrame, $width, $height)];
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

    public function upscale(TaromboFrame $taromboFrame, TaromboFrameUpscaler $upscaler): RedirectResponse
    {
        if (! Storage::disk('local')->exists($taromboFrame->path)) {
            throw ValidationException::withMessages([
                'frame' => 'Gambar frame tidak ditemukan di server. Ganti gambarnya lewat tombol Ubah terlebih dahulu.',
            ]);
        }

        try {
            $result = $upscaler->upscale($taromboFrame);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['frame' => $exception->getMessage()]);
        }

        $oldPath = $taromboFrame->path;

        $taromboFrame->update([
            'path' => $result['path'],
            ...$this->scaledArea($taromboFrame, $result['width'], $result['height']),
        ]);

        Storage::disk('local')->delete($oldPath);

        return back()->with('toast', ['type' => 'success', 'message' => 'Resolusi frame berhasil ditingkatkan.']);
    }

    public function updateArea(UpdateTaromboFrameAreaRequest $request, TaromboFrame $taromboFrame): RedirectResponse
    {
        $taromboFrame->update($request->validated());

        return back()->with('toast', ['type' => 'success', 'message' => 'Area konten frame berhasil disimpan.']);
    }

    public function destroy(TaromboFrame $taromboFrame): RedirectResponse
    {
        Storage::disk('local')->delete($taromboFrame->path);
        $taromboFrame->delete();

        return back()->with('toast', ['type' => 'success', 'message' => 'Template frame berhasil dihapus.']);
    }

    /**
     * Keep the admin-defined content area in place when the frame image changes size.
     *
     * @return array{canvas_width: int, canvas_height: int, area_x: int, area_y: int, area_width: int, area_height: int}
     */
    private function scaledArea(TaromboFrame $frame, int $width, int $height): array
    {
        $ratioX = $width / max(1, $frame->canvas_width);
        $ratioY = $height / max(1, $frame->canvas_height);
        $areaX = min($width - 1, (int) round($frame->area_x * $ratioX));
        $areaY = min($height - 1, (int) round($frame->area_y * $ratioY));

        return [
            'canvas_width' => $width,
            'canvas_height' => $height,
            'area_x' => $areaX,
            'area_y' => $areaY,
            'area_width' => max(1, min($width - $areaX, (int) round($frame->area_width * $ratioX))),
            'area_height' => max(1, min($height - $areaY, (int) round($frame->area_height * $ratioY))),
        ];
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
            'is_collage' => $frame->is_collage,
        ];
    }
}

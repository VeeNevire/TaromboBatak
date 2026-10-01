<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttachTaromboQrRequest;
use App\Models\TaromboSnapshot;
use App\Models\User;
use App\Services\TaromboQrComposer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaromboQrController extends Controller
{
    public function create(Request $request, TaromboSnapshot $taromboSnapshot, TaromboQrComposer $composer): Response
    {
        abort_unless($taromboSnapshot->tarombo_frame_id !== null, 404);
        $snapshot = $taromboSnapshot;
        $token = (string) Str::uuid();
        $url = route('tarombo.qr.show', ['token' => $token]);
        $qr = $composer->qrImage($url);
        $qrImageUrl = 'data:image/png;base64,'.base64_encode($composer->png($qr));
        imagedestroy($qr);
        $exists = Storage::disk('local')->exists($snapshot->path);
        $dimensions = $exists ? getimagesize(Storage::disk('local')->path($snapshot->path)) : false;

        return Inertia::render('tarombo/attach-qr', [
            'snapshot' => ['id' => $snapshot->id, 'title' => $snapshot->title ?? $snapshot->centerPerson?->name ?? 'Pohon Tarombo', 'image_url' => route('tarombo.snapshots.image', $snapshot)],
            'token' => $token,
            'qrModules' => $composer->moduleCount($url),
            'qrImageUrl' => $qrImageUrl,
            'imageSize' => ['width' => $dimensions[0] ?? 1, 'height' => $dimensions[1] ?? 1],
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'email']),
            'hasPreview' => $exists && $dimensions !== false,
        ]);
    }

    public function code(string $token, TaromboQrComposer $composer): HttpResponse
    {
        abort_unless(Str::isUuid($token), 404);
        $qr = $composer->qrImage(route('tarombo.qr.show', ['token' => $token]));
        $bytes = $composer->png($qr);
        imagedestroy($qr);

        return response($bytes, 200, ['Content-Type' => 'image/png', 'Cache-Control' => 'private, no-store']);
    }

    public function store(AttachTaromboQrRequest $request, TaromboSnapshot $taromboSnapshot, TaromboQrComposer $composer): RedirectResponse
    {
        abort_unless($taromboSnapshot->tarombo_frame_id !== null, 404);
        $recipient = User::query()->findOrFail($request->validated('user_id'));
        $composer->attach($taromboSnapshot, $recipient, $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Gambar dengan QR berhasil disimpan ke Hasil Compile akun '.$recipient->name.'.']);

        return to_route('tarombo.snapshots.index', ['filter' => 'compiled', 'display' => 'images']);
    }

    public function show(string $token): Response
    {
        $snapshot = TaromboSnapshot::query()->where('qr_token', $token)->firstOrFail();

        return Inertia::render('tarombo/qr-result', ['snapshot' => [
            'title' => $snapshot->title,
            'image_url' => route('tarombo.qr.image', ['token' => $token]),
            'download_url' => route('tarombo.qr.download', ['token' => $token]),
        ]]);
    }

    public function image(string $token): StreamedResponse
    {
        return $this->serve($token, 'inline');
    }

    public function download(string $token): StreamedResponse
    {
        return $this->serve($token, 'attachment');
    }

    private function serve(string $token, string $disposition): StreamedResponse
    {
        $snapshot = TaromboSnapshot::query()->where('qr_token', $token)->firstOrFail();
        $disk = Storage::disk('local');
        abort_unless($disk->exists($snapshot->path), 404);

        return $disk->response($snapshot->path, Str::slug($snapshot->title ?? 'tarombo').'.png', [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ], $disposition);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\MessageAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MessageAttachmentController extends Controller
{
    public function show(Request $request, MessageAttachment $messageAttachment): StreamedResponse
    {
        $messageAttachment->loadMissing('message.conversation');
        abort_unless(
            $messageAttachment->message->conversation->includes($request->user()),
            403,
        );

        $disk = Storage::disk($messageAttachment->disk);
        abort_unless($disk->exists($messageAttachment->path), 404);

        if ($request->boolean('download')) {
            return $disk->download(
                $messageAttachment->path,
                $messageAttachment->original_name,
                ['X-Content-Type-Options' => 'nosniff'],
            );
        }

        return $disk->response(
            $messageAttachment->path,
            $messageAttachment->original_name,
            [
                'Content-Type' => $messageAttachment->mime_type,
                'X-Content-Type-Options' => 'nosniff',
                'Content-Security-Policy' => "default-src 'none'; sandbox",
            ],
        );
    }
}

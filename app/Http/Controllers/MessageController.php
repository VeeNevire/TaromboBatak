<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Http\Requests\StoreMessageRequest;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class MessageController extends Controller
{
    public function store(StoreMessageRequest $request, User $contact): RedirectResponse
    {
        /** @var User $sender */
        $sender = $request->user();

        $storedPaths = [];

        try {
            $message = DB::transaction(function () use ($request, $sender, $contact, &$storedPaths): Message {
                $conversation = Conversation::query()->firstOrCreate(
                    Conversation::participantAttributes($sender, $contact),
                );

                $message = $conversation->messages()->create([
                    'sender_id' => $sender->id,
                    'body' => $request->validated('body'),
                ]);

                foreach ($request->file('attachments', []) as $file) {
                    $path = $file->store("chat-attachments/{$conversation->id}", 'local');

                    if ($path === false) {
                        throw new RuntimeException('Lampiran pesan gagal disimpan.');
                    }

                    $storedPaths[] = $path;
                    $mimeType = $file->getMimeType() ?: 'application/octet-stream';
                    $originalName = preg_replace(
                        '/[\x00-\x1F\x7F]/u',
                        '',
                        basename($file->getClientOriginalName()),
                    ) ?: 'lampiran';
                    $message->attachments()->create([
                        'disk' => 'local',
                        'path' => $path,
                        'original_name' => Str::limit($originalName, 240, ''),
                        'mime_type' => $mimeType,
                        'size' => $file->getSize(),
                        'category' => MessageAttachment::categoryFor($mimeType),
                    ]);
                }

                return $message->load('attachments');
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedPaths);

            throw $exception;
        }

        try {
            MessageSent::dispatch($message);
        } catch (Throwable $exception) {
            // Pesan sudah tersimpan; kegagalan broadcast tidak boleh
            // membuat pengirim mengira pesannya gagal terkirim.
            report($exception);
        }

        return to_route('contacts.show', $contact);
    }
}

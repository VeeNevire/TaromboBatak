<?php

namespace App\Http\Controllers;

use App\Events\MargaMessageSent;
use App\Http\Requests\StoreMargaMessageRequest;
use App\Models\Marga;
use App\Models\MargaChatRead;
use App\Models\MargaMessage;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MargaChatController extends Controller
{
    /**
     * Shared chat room for a marga. Readable and writable by any signed-in
     * user; the marga's own members and managers are listed as participants.
     */
    public function show(Request $request, Marga $marga): Response
    {
        /** @var User $viewer */
        $viewer = $request->user();

        MargaChatRead::query()->updateOrCreate(
            ['user_id' => $viewer->id, 'marga_id' => $marga->id],
            ['last_read_at' => now()],
        );

        $members = $marga->managedByUsers()
            ->get(['users.id', 'users.name'])
            ->merge(
                User::query()
                    ->where('marga_id', $marga->id)
                    ->where('role', '!=', 'admin')
                    ->orderBy('name')
                    ->get(['id', 'name']),
            )
            ->unique('id')
            ->sortBy('name')
            ->values();

        return Inertia::render('marga/chat', [
            'marga' => [
                'id' => $marga->id,
                'name' => $marga->name,
                'color' => $marga->color,
            ],
            'members' => $members->map(fn (User $member) => [
                'id' => $member->id,
                'name' => $member->name,
            ]),
            'messages' => $marga->messages()
                ->with('sender:id,name')
                ->latest('id')
                ->limit(100)
                ->get()
                ->reverse()
                ->values()
                ->map(fn (MargaMessage $message) => [
                    'id' => $message->id,
                    'sender_id' => $message->sender_id,
                    'sender_name' => $message->sender?->name ?? 'Pengguna terhapus',
                    'body' => $message->body,
                    'created_at' => $message->created_at?->toISOString(),
                ]),
        ]);
    }

    public function store(StoreMargaMessageRequest $request, Marga $marga): RedirectResponse
    {
        /** @var User $sender */
        $sender = $request->user();

        $message = $marga->messages()->create([
            'sender_id' => $sender->id,
            'body' => $request->validated('body'),
        ]);

        MargaMessageSent::dispatch($message);

        return back();
    }
}

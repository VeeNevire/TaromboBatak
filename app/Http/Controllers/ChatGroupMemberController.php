<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateChatGroupMembersRequest;
use App\Models\ChatGroup;
use App\Models\ChatGroupMember;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ChatGroupMemberController extends Controller
{
    public function update(UpdateChatGroupMembersRequest $request, ChatGroup $chatGroup): RedirectResponse
    {
        $members = collect($request->memberIds())
            ->push($chatGroup->owner_id)
            ->unique()
            ->mapWithKeys(fn (int $id): array => [$id => [
                'role' => $id === $chatGroup->owner_id ? ChatGroupMember::ROLE_OWNER : ChatGroupMember::ROLE_MEMBER,
            ]]);
        $chatGroup->members()->sync($members->all());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Anggota grup diperbarui.']);

        return to_route('groups.show', $chatGroup);
    }
}

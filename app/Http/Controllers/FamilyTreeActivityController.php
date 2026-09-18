<?php

namespace App\Http\Controllers;

use App\Models\FamilyTreeActivity;
use App\Models\FamilyTreeShare;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FamilyTreeActivityController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $activities = FamilyTreeActivity::query()
            ->with(['actor:id,name', 'familyTree.rootPerson:id,name'])
            ->when(! $user->isStaff(), fn ($query) => $query->where(fn ($access) => $access
                ->where('owner_id', $user->id)
                ->orWhereHas('familyTree.shares', fn ($shares) => $shares
                    ->whereBelongsTo($user, 'recipient')
                    ->where('status', FamilyTreeShare::STATUS_ACCEPTED))))
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (FamilyTreeActivity $activity) => [
                'id' => $activity->id,
                'tree_name' => $activity->tree_name,
                'father_name' => $activity->familyTree?->rootPerson?->name,
                'member_name' => $activity->member_name,
                'action' => $activity->action,
                'description' => $activity->description,
                'actor' => $activity->actor?->name ?? 'Sistem',
                'created_at' => $activity->created_at?->translatedFormat('d M Y, H:i'),
            ]);

        return Inertia::render('family-tree-activities/index', [
            'activities' => $activities,
        ]);
    }
}

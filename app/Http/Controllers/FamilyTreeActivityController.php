<?php

namespace App\Http\Controllers;

use App\Http\Requests\FilterFamilyTreeActivitiesRequest;
use App\Services\FamilyTreeActivityHistory;
use Inertia\Inertia;
use Inertia\Response;

class FamilyTreeActivityController extends Controller
{
    public function index(FilterFamilyTreeActivitiesRequest $request, FamilyTreeActivityHistory $history): Response
    {
        $filters = $request->validated();
        $activities = $history->paginate($request->user(), $filters);

        return Inertia::render('family-tree-activities/index', [
            'activities' => $activities->items(),
            'pagination' => [
                'current_page' => $activities->currentPage(),
                'last_page' => $activities->lastPage(),
                'total' => $activities->total(),
            ],
            'accounts' => $history->accounts($request->user()),
            'filters' => [
                'search' => trim($filters['search'] ?? ''),
                'account_id' => $filters['account_id'] ?? null,
                'date' => $filters['date'] ?? '',
                'order' => $filters['order'] ?? 'newest',
            ],
        ]);
    }
}

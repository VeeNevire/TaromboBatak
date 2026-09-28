<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\MargaNewsAgentController;
use App\Http\Requests\MargaNewsTopicRequest;
use App\Models\Marga;
use App\Models\MargaNewsTopic;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

/** Admin list of the keywords the news agent searches for. */
class MargaNewsTopicController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('marga-news/topics', [
            'topics' => MargaNewsTopic::query()
                ->with('marga:id,name')
                ->withCount('news')
                ->orderByDesc('is_active')
                ->orderBy('keyword')
                ->get()
                ->map(fn (MargaNewsTopic $topic) => [
                    'id' => $topic->id,
                    'keyword' => $topic->keyword,
                    'marga_id' => $topic->marga_id,
                    'marga' => $topic->marga?->name,
                    'is_active' => $topic->is_active,
                    'notes' => $topic->notes,
                    'news_count' => $topic->news_count,
                ]),
            'margas' => Marga::query()->orderBy('name')->get(['id', 'name']),
            'agent' => [
                'configured' => filled(config('services.marga_news.agent_token')),
                'last_seen' => Cache::get(MargaNewsAgentController::LAST_SEEN_KEY),
                'last_ingest' => Cache::get(MargaNewsAgentController::LAST_INGEST_KEY),
                'tasks_url' => route('api.marga-news.tasks'),
                'ingest_url' => route('api.marga-news.ingest'),
            ],
        ]);
    }

    public function store(MargaNewsTopicRequest $request): RedirectResponse
    {
        MargaNewsTopic::query()->create($request->validated());

        return back()->with('toast', ['type' => 'success', 'message' => 'Topik berita ditambahkan.']);
    }

    public function update(MargaNewsTopicRequest $request, MargaNewsTopic $topic): RedirectResponse
    {
        $topic->update($request->validated());

        return back()->with('toast', ['type' => 'success', 'message' => 'Topik berita diperbarui.']);
    }

    public function destroy(MargaNewsTopic $topic): RedirectResponse
    {
        $topic->delete();

        return back()->with('toast', ['type' => 'success', 'message' => 'Topik berita dihapus.']);
    }
}

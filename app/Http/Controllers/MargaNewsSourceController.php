<?php

namespace App\Http\Controllers;

use App\Http\Requests\MargaNewsSourceRequest;
use App\Models\MargaNewsSource;
use App\Models\MargaNewsTopic;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class MargaNewsSourceController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('marga-news/sources', [
            'sources' => MargaNewsSource::query()
                ->with('topics:id,keyword')
                ->withCount('news')
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get()
                ->map(fn (MargaNewsSource $source) => [
                    'id' => $source->id,
                    'name' => $source->name,
                    'website_url' => $source->website_url,
                    'domain' => $source->domain,
                    'is_active' => $source->is_active,
                    'applies_to_all_topics' => $source->applies_to_all_topics,
                    'topic_ids' => $source->topics->pluck('id')->all(),
                    'topics' => $source->topics->pluck('keyword')->all(),
                    'notes' => $source->notes,
                    'news_count' => $source->news_count,
                ]),
            'topics' => MargaNewsTopic::query()->orderBy('keyword')->get(['id', 'keyword']),
        ]);
    }

    public function store(MargaNewsSourceRequest $request): RedirectResponse
    {
        $values = $request->validatedSource();
        $topicIds = $values['topic_ids'];
        unset($values['topic_ids']);

        DB::transaction(function () use ($values, $topicIds): void {
            $source = MargaNewsSource::query()->create($values);
            $source->topics()->sync($topicIds);
        });

        return back()->with('toast', ['type' => 'success', 'message' => 'Sumber berita ditambahkan.']);
    }

    public function update(MargaNewsSourceRequest $request, MargaNewsSource $source): RedirectResponse
    {
        $values = $request->validatedSource();
        $topicIds = $values['topic_ids'];
        unset($values['topic_ids']);

        DB::transaction(function () use ($source, $values, $topicIds): void {
            $source->update($values);
            $source->topics()->sync($topicIds);
        });

        return back()->with('toast', ['type' => 'success', 'message' => 'Sumber berita diperbarui.']);
    }

    public function destroy(MargaNewsSource $source): RedirectResponse
    {
        $source->delete();

        return back()->with('toast', ['type' => 'success', 'message' => 'Sumber berita dihapus.']);
    }
}

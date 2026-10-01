<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReviewMargaNewsRequest;
use App\Http\Requests\StoreMargaNewsCommentRequest;
use App\Http\Requests\UpdateMargaNewsMargasRequest;
use App\Models\Marga;
use App\Models\MargaNews;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class MargaNewsController extends Controller
{
    /** Public list of approved news, filterable by marga. */
    public function index(Request $request): Response
    {
        $margaId = $request->integer('marga') ?: null;
        $search = trim((string) $request->string('q'));

        $news = MargaNews::query()
            ->approved()
            ->with('margas:id,name,color')
            ->when($margaId, fn ($query) => $query->whereHas('margas', fn ($margas) => $margas->whereKey($margaId)))
            ->when($search !== '', fn ($query) => $query->where('title', 'like', '%'.$search.'%'))
            ->latest('updated_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (MargaNews $item) => $this->newsData($item));

        return Inertia::render('marga-news/index', [
            'news' => $news,
            'filters' => ['marga' => $margaId, 'q' => $search],
            // Only margas that have approved news are worth filtering by.
            'margas' => Marga::query()
                ->whereHas('news', fn ($query) => $query->approved())
                ->orderBy('name')
                ->get(['id', 'name']),
            'myMargaId' => $request->user()?->marga_id,
        ]);
    }

    public function show(MargaNews $margaNews): Response
    {
        abort_unless($margaNews->status === MargaNews::STATUS_APPROVED, 404);
        $margaNews->load('margas:id,name,color');

        return Inertia::render('marga-news/show', [
            'news' => $this->newsData($margaNews),
            'comments' => $margaNews->comments()->with('author:id,name')->oldest()->oldest('id')
                ->paginate(20)->withQueryString()->through(fn ($comment) => [
                    'id' => $comment->id,
                    'author' => $comment->author->name,
                    'body' => $comment->body,
                    'created_at' => $comment->created_at?->toIso8601String(),
                ]),
        ]);
    }

    public function comment(StoreMargaNewsCommentRequest $request, MargaNews $margaNews): RedirectResponse
    {
        abort_unless($margaNews->status === MargaNews::STATUS_APPROVED, 404);
        $margaNews->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $request->validated('body'),
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => 'Komentar ditambahkan.']);
    }

    /** Staff review queue of what the news agent found. */
    public function review(Request $request): Response
    {
        $status = $request->string('status')->toString();
        $status = in_array($status, [MargaNews::STATUS_PENDING, MargaNews::STATUS_APPROVED, MargaNews::STATUS_REJECTED, MargaNews::STATUS_INACTIVE], true)
            ? $status
            : MargaNews::STATUS_PENDING;

        return Inertia::render('marga-news/review', [
            'status' => $status,
            'counts' => MargaNews::query()
                ->select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->pluck('total', 'status'),
            'pendingHermesCount' => MargaNews::query()
                ->where('status', MargaNews::STATUS_PENDING)
                ->whereNotNull('marga_news_topic_id')
                ->count(),
            'news' => MargaNews::query()
                ->where('status', $status)
                ->with(['margas:id,name,color', 'topic:id,keyword', 'reviewer:id,name'])
                ->latest('updated_at')->latest('id')
                ->paginate(20)
                ->withQueryString()
                ->through(fn (MargaNews $item) => [
                    ...$this->newsData($item),
                    'topic' => $item->topic?->keyword,
                    'submitted_by' => $item->submitted_by,
                    'reviewer' => $item->reviewer?->name,
                    'reviewed_at' => $item->reviewed_at?->toIso8601String(),
                    'created_at' => $item->created_at?->toIso8601String(),
                ]),
            'margas' => Marga::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** Approve or reject one or more articles. */
    public function decide(ReviewMargaNewsRequest $request): RedirectResponse
    {
        $status = match ($request->validated('action')) {
            'approve' => MargaNews::STATUS_APPROVED,
            'deactivate' => MargaNews::STATUS_INACTIVE,
            default => MargaNews::STATUS_REJECTED,
        };

        $count = MargaNews::query()
            ->whereKey($request->validated('ids'))
            ->update([
                'status' => $status,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);

        return back()->with('toast', [
            'type' => 'success',
            'message' => $status === MargaNews::STATUS_APPROVED
                ? "{$count} berita disetujui dan tampil di Berita Marga-Marga."
                : ($status === MargaNews::STATUS_INACTIVE ? "{$count} berita dinonaktifkan." : "{$count} berita ditolak."),
        ]);
    }

    /** Approve every pending article collected by Hermes. */
    public function approveAllHermes(Request $request): RedirectResponse
    {
        $count = MargaNews::query()
            ->where('status', MargaNews::STATUS_PENDING)
            ->whereNotNull('marga_news_topic_id')
            ->update([
                'status' => MargaNews::STATUS_APPROVED,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);

        return back()->with('toast', [
            'type' => 'success',
            'message' => "{$count} berita dari Hermes disetujui dan tampil di Berita Marga-Marga.",
        ]);
    }

    public function updateMargas(UpdateMargaNewsMargasRequest $request, MargaNews $margaNews): RedirectResponse
    {
        $margaNews->margas()->sync($request->validated('marga_ids') ?? []);

        return back()->with('toast', ['type' => 'success', 'message' => 'Tag marga diperbarui.']);
    }

    /** @return array<string, mixed> */
    private function newsData(MargaNews $item): array
    {
        return [
            'id' => $item->id,
            'title' => $item->title,
            'url' => $item->url,
            'publisher' => $item->publisher,
            'excerpt' => $item->excerpt,
            'summary' => $item->summary,
            'content' => $item->content,
            'image_url' => $item->image_url,
            'published_at' => $item->published_at?->toIso8601String(),
            'updated_at' => $item->updated_at?->toIso8601String(),
            'margas' => $item->margas->map(fn (Marga $marga) => [
                'id' => $marga->id,
                'name' => $marga->name,
                'color' => $marga->color,
            ])->values(),
        ];
    }
}

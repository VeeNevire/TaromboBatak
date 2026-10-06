<?php

namespace App\Http\Controllers;

use App\Http\Requests\AskTaromboRequest;
use App\Models\Marga;
use App\Services\HermesRunClient;
use App\Services\TaromboTreeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class TaromboAiController extends Controller
{
    public function select(): Response
    {
        abort_unless(request()->user()?->isStaff() || request()->user()?->isContributor(), 403);

        return Inertia::render('marga/select', [
            'feature' => 'ai',
            'margas' => Marga::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(Marga $marga): Response
    {
        $this->authorizeAccess($marga);

        return Inertia::render('marga/tanya-tarombo', [
            'marga' => $marga->only(['id', 'name', 'color', 'description']),
        ]);
    }

    public function ask(AskTaromboRequest $request, Marga $marga, HermesRunClient $hermes, TaromboTreeService $tree): RedirectResponse
    {
        $this->authorizeAccess($marga);
        $question = $request->validated('question');

        try {
            $run = $hermes->runAndWait([
                'input' => json_encode([
                    'task' => 'tanya_tarombo',
                    'question' => $question,
                    'marga' => [
                        'name' => $marga->name,
                        'description' => $marga->description,
                        'identity_person' => $marga->identityPerson?->name,
                    ],
                    'tree_rows' => collect($tree->rowsForMarga($marga, 'lower', maxDepth: 8, maxNodes: 500))
                        ->map(fn (array $row) => collect($row)->only([
                            'id', 'name', 'parentId', 'marga', 'gender', 'birthYear',
                        ])->all())->values()->all(),
                ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'instructions' => 'Kamu adalah Ito Tarombo, asisten tanya jawab tarombo. Jawab pertanyaan pada input dalam bahasa Indonesia dengan sopan. Gunakan hanya informasi marga dan tree_rows yang tersedia. Jika data tidak cukup, katakan terus terang. Jangan mengarang silsilah. Kembalikan jawaban sebagai teks biasa, bukan daftar berita atau object JSON.',
            ]);
            $answer = $run['output'] ?? $run['result'] ?? $run['response'] ?? null;
            $answer = is_array($answer) ? ($answer['answer'] ?? $answer['text'] ?? json_encode($answer, JSON_UNESCAPED_UNICODE)) : (string) $answer;

            Inertia::flash('tarombo_answer', trim($answer) ?: 'Hermes belum memberikan jawaban.');

            return back();
        } catch (Throwable $exception) {
            report($exception);

            Inertia::flash('tarombo_error', Str::contains($exception->getMessage(), 'HERMES_BASE_URL')
                ? 'Layanan Hermes belum dikonfigurasi.'
                : 'Ito Tarombo sedang tidak tersedia. Coba lagi sebentar.');

            return back();
        }
    }

    private function authorizeAccess(Marga $marga): void
    {
        $user = request()->user();
        abort_unless($user?->isStaff() || $user?->isContributorOf($marga->id), 403);
    }
}

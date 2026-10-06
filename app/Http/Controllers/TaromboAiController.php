<?php

namespace App\Http\Controllers;

use App\Http\Requests\AskTaromboRequest;
use App\Models\Marga;
use App\Models\Person;
use App\Models\TaromboAiConversation;
use App\Services\HermesRunClient;
use App\Services\TaromboTreeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class TaromboAiController extends Controller
{
    public function select(): Response
    {
        $this->authorizeAccess();

        return $this->chatPage();
    }

    public function show(Marga $marga): Response
    {
        $this->authorizeAccess($marga);

        return $this->chatPage($marga);
    }

    private function chatPage(?Marga $marga = null): Response
    {
        $conversation = $this->currentConversation($marga);

        return Inertia::render('marga/tanya-tarombo', [
            'marga' => $marga?->only(['id', 'name', 'color', 'description']),
            'margas' => $this->availableMargas()->orderBy('name')->get(['id', 'name']),
            'conversationId' => $conversation->id,
            'messages' => $conversation->messages()->orderBy('id')->get(['id', 'role', 'text']),
            'conversations' => $this->conversationQuery($marga)->latest('id')->limit(30)->get(['id', 'created_at']),
        ]);
    }

    public function newConversation(Marga $marga): RedirectResponse
    {
        $this->authorizeAccess($marga);
        TaromboAiConversation::create(['user_id' => request()->user()->id, 'marga_id' => $marga->id]);

        return to_route('marga.ai.show', $marga);
    }

    public function newGeneralConversation(): RedirectResponse
    {
        $this->authorizeAccess();
        TaromboAiConversation::create(['user_id' => request()->user()->id]);

        return to_route('marga.ai.select');
    }

    private function conversationQuery(?Marga $marga): Builder
    {
        return TaromboAiConversation::query()->where('user_id', request()->user()->id)->where('marga_id', $marga?->id);
    }

    private function currentConversation(?Marga $marga): TaromboAiConversation
    {
        if (request()->filled('conversation')) {
            return $this->conversationQuery($marga)->findOrFail(request()->integer('conversation'));
        }

        return $this->conversationQuery($marga)->latest('id')->first()
            ?? TaromboAiConversation::create(['user_id' => request()->user()->id, 'marga_id' => $marga?->id]);
    }

    private function availableMargas(): Builder
    {
        $user = request()->user();

        return Marga::query()->when(! $user->isStaff(), fn ($query) => $query->whereIn('id', $user->accessibleMargaIds()));
    }

    public function askAll(AskTaromboRequest $request, HermesRunClient $hermes, TaromboTreeService $tree): RedirectResponse
    {
        return $this->answer($request, $hermes, $tree);
    }

    public function ask(AskTaromboRequest $request, Marga $marga, HermesRunClient $hermes, TaromboTreeService $tree): RedirectResponse
    {
        return $this->answer($request, $hermes, $tree, $marga);
    }

    private function answer(AskTaromboRequest $request, HermesRunClient $hermes, TaromboTreeService $tree, ?Marga $marga = null): RedirectResponse
    {
        $this->authorizeAccess($marga);
        $conversation = $request->filled('conversation_id')
            ? $this->conversationQuery($marga)->findOrFail($request->integer('conversation_id'))
            : $this->currentConversation($marga);
        $question = $request->validated('question');
        $history = $conversation->messages()->latest('id')->limit(20)->get(['role', 'text'])->reverse()->values()->all();
        $conversation->messages()->create(['role' => 'user', 'text' => $question]);

        try {
            $run = $hermes->runAndWait([
                'input' => json_encode([
                    'task' => 'tanya_tarombo',
                    'question' => $question,
                    'conversation_history' => $history,
                    'marga' => $marga === null ? null : [
                        'name' => $marga->name,
                        'description' => $marga->description,
                        'identity_person' => $marga->identityPerson?->name,
                    ],
                    'margas' => $marga === null ? $this->availableMargas()->withCount('people')->get(['id', 'name', 'description'])->toArray() : [],
                    'context_is_limited' => true,
                    'tree_rows' => collect($marga !== null ? $tree->rowsForMarga($marga, 'lower', maxDepth: 8, maxNodes: 500) : $this->generalRows())
                        ->map(fn (array $row) => collect($row)->only([
                            'id', 'name', 'parentId', 'marga', 'gender', 'birthYear',
                        ])->all())->values()->all(),
                ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'instructions' => 'Kamu adalah Ito Tarombo, asisten tanya jawab tarombo. Jawab pertanyaan pada input dalam bahasa Indonesia dengan sopan. Gunakan riwayat percakapan untuk memahami pertanyaan lanjutan. Gunakan hanya informasi marga, margas dan tree_rows yang tersedia. Konteks tree_rows dibatasi, jadi jangan menyimpulkan total anggota dari jumlah baris; gunakan people_count bila tersedia. Jika data tidak cukup, katakan terus terang. Jangan mengarang silsilah. Kembalikan jawaban sebagai teks biasa, bukan daftar berita atau object JSON.',
            ]);
            $answer = $run['output'] ?? $run['result'] ?? $run['response'] ?? null;
            $answer = is_array($answer) ? ($answer['answer'] ?? $answer['text'] ?? json_encode($answer, JSON_UNESCAPED_UNICODE)) : (string) $answer;

            $answer = trim($answer) ?: 'Hermes belum memberikan jawaban.';
            $conversation->messages()->create(['role' => 'assistant', 'text' => $answer]);
            Inertia::flash('tarombo_answer', $answer);

            return back();
        } catch (Throwable $exception) {
            report($exception);

            $error = Str::contains($exception->getMessage(), 'HERMES_BASE_URL')
                ? 'Layanan Hermes belum dikonfigurasi.'
                : 'Ito Tarombo sedang tidak tersedia. Coba lagi sebentar.';
            $conversation->messages()->create(['role' => 'assistant', 'text' => $error]);
            Inertia::flash('tarombo_error', $error);

            return back();
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function generalRows(): array
    {
        return Person::query()->whereIn('marga_id', $this->availableMargas()->select('id'))
            ->with('marga:id,name')->orderBy('id')->limit(500)
            ->get(['id', 'name', 'father_id', 'marga_id', 'gender', 'birth_year'])
            ->map(fn (Person $person) => [
                'id' => (string) $person->id,
                'name' => $person->name,
                'parentId' => $person->father_id === null ? null : (string) $person->father_id,
                'marga' => $person->marga?->name,
                'gender' => $person->gender,
                'birthYear' => $person->birth_year,
            ])->all();
    }

    private function authorizeAccess(?Marga $marga = null): void
    {
        $user = request()->user();
        abort_unless($user?->isStaff() || ($marga !== null ? $user?->isContributorOf($marga->id) : $user?->isContributor()), 403);
    }
}

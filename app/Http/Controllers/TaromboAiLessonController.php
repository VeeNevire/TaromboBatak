<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaromboAiLessonRequest;
use App\Http\Requests\UpdateTaromboAiLessonRequest;
use App\Models\TaromboAiLesson;
use Illuminate\Http\RedirectResponse;

class TaromboAiLessonController extends Controller
{
    public function store(StoreTaromboAiLessonRequest $request): RedirectResponse
    {
        TaromboAiLesson::query()->create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => 'Materi pembelajaran AI berhasil ditambahkan.']);
    }

    public function update(UpdateTaromboAiLessonRequest $request, TaromboAiLesson $lesson): RedirectResponse
    {
        $lesson->update([
            ...$request->validated(),
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => 'Materi pembelajaran AI berhasil diperbarui.']);
    }

    public function destroy(TaromboAiLesson $lesson): RedirectResponse
    {
        abort_unless(request()->user()?->isAdmin(), 403);
        $lesson->delete();

        return back()->with('toast', ['type' => 'success', 'message' => 'Materi pembelajaran AI dihapus.']);
    }
}

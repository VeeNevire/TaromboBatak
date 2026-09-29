<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateMargaNewsAutomationRequest;
use App\Models\MargaNewsAutomationSetting;
use App\Models\MargaNewsSource;
use App\Models\MargaNewsTopic;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class MargaNewsAutomationController extends Controller
{
    public function index(): Response
    {
        $setting = MargaNewsAutomationSetting::current();

        return Inertia::render('marga-news/automation', [
            'automation' => [
                'enabled' => $setting->enabled,
                'interval_minutes' => $setting->interval_minutes,
                'prompt' => $setting->prompt ?? '',
                'next_run_at' => $setting->next_run_at?->toIso8601String(),
                'last_status' => $setting->last_status,
                'last_started_at' => $setting->last_started_at?->toIso8601String(),
                'last_finished_at' => $setting->last_finished_at?->toIso8601String(),
                'last_accepted' => $setting->last_accepted,
                'last_duplicates' => $setting->last_duplicates,
                'last_error' => $setting->last_error,
            ],
            'hermes' => [
                'configured' => filled(config('services.hermes.base_url')),
            ],
        ]);
    }

    public function update(UpdateMargaNewsAutomationRequest $request): RedirectResponse
    {
        $values = $request->validated();
        $enabled = (bool) $values['enabled'];
        $interval = (int) $values['interval_minutes'];

        if ($enabled && ! filled(config('services.hermes.base_url'))) {
            throw ValidationException::withMessages([
                'enabled' => 'Isi HERMES_BASE_URL Hermes di .env sebelum mengaktifkan otomatisasi.',
            ]);
        }

        if ($enabled && ! MargaNewsSource::query()->where('is_active', true)->exists()) {
            throw ValidationException::withMessages([
                'enabled' => 'Tambahkan dan aktifkan minimal satu website di menu Sumber Website Berita sebelum mengaktifkan otomatisasi.',
            ]);
        }

        if ($enabled && ! MargaNewsTopic::query()->where('is_active', true)->exists()) {
            throw ValidationException::withMessages([
                'enabled' => 'Tambahkan dan aktifkan minimal satu topik di menu Topik Berita Marga sebelum mengaktifkan otomatisasi.',
            ]);
        }

        MargaNewsAutomationSetting::current()->update([
            'enabled' => $enabled,
            'interval_minutes' => $interval,
            'prompt' => $values['prompt'] ?? null,
            // Start soon after saving. The selected interval applies after the
            // first completed run, in MargaNewsAutomationRunner::finish().
            'next_run_at' => $enabled ? now() : null,
            'last_error' => null,
        ]);

        return back()->with('toast', [
            'type' => 'success',
            'message' => $enabled
                ? "Otomatisasi berita aktif. Pencarian pertama dimulai pada siklus scheduler berikutnya; selanjutnya setiap {$interval} menit."
                : 'Otomatisasi berita dinonaktifkan.',
        ]);
    }
}

<?php

use App\Http\Controllers\Api\MargaNewsAgentController;
use App\Http\Middleware\AuthenticateMargaNewsAgent;
use Illuminate\Support\Facades\Route;

// Used by the news agent (Hermes Agent); see docs/hermes-agent-berita-marga.md.
Route::middleware([AuthenticateMargaNewsAgent::class, 'throttle:30,1'])
    ->prefix('berita-marga')
    ->name('api.marga-news.')
    ->group(function (): void {
        Route::get('tugas', [MargaNewsAgentController::class, 'tasks'])->name('tasks');
        Route::post('masuk', [MargaNewsAgentController::class, 'ingest'])->name('ingest');
    });

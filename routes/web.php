<?php

use App\Http\Controllers\BudayaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\KomunitasController;
use App\Http\Controllers\MargaController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\StoryController;
use App\Http\Controllers\TaromboController;
use App\Http\Controllers\TentangController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('tarombo', [TaromboController::class, 'public'])->name('tarombo.view');

Route::get('marga', [MargaController::class, 'public'])->name('marga.view');

Route::get('budaya', [BudayaController::class, 'index'])->name('budaya.view');

Route::get('komunitas', [KomunitasController::class, 'index'])->name('komunitas.view');

Route::get('tentang', [TentangController::class, 'index'])->name('tentang.view');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('dashboard/tarombo', [TaromboController::class, 'index'])->name('tarombo.index');

    Route::get('people', [PersonController::class, 'index'])->name('people.index');
});

Route::middleware(['auth', 'verified', 'role.admin'])->group(function () {
    Route::resource('people', PersonController::class)
        ->only(['create', 'store', 'edit', 'update', 'destroy', 'show']);

    Route::get('people/{person}/preview', [PersonController::class, 'preview'])->name('people.preview');
    Route::get('people/{person}/silsilah', [PersonController::class, 'silsilah'])->name('people.silsilah');

    Route::get('dashboard/marga', [MargaController::class, 'index'])->name('marga.index');
    Route::post('dashboard/marga', [MargaController::class, 'store'])->name('marga.store');
    Route::put('dashboard/marga/{marga}', [MargaController::class, 'update'])->name('marga.update');
    Route::delete('dashboard/marga/{marga}', [MargaController::class, 'destroy'])->name('marga.destroy');

    Route::resource('stories', StoryController::class)->except(['show']);

    Route::resource('events', EventController::class)->except(['show']);
});

require __DIR__.'/settings.php';

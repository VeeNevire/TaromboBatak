<?php

use App\Http\Controllers\BudayaController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ContributionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\FamilyTreeDeletionController;
use App\Http\Controllers\FeedCommentController;
use App\Http\Controllers\FeedPostController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\KomunitasController;
use App\Http\Controllers\MargaController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NewsFeedController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\StoryController;
use App\Http\Controllers\SubAdminController;
use App\Http\Controllers\TaromboController;
use App\Http\Controllers\TentangController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('tarombo', [TaromboController::class, 'public'])->name('tarombo.view');

Route::get('tarombo/full', [TaromboController::class, 'publicFullscreen'])
    ->name('tarombo.full');

Route::get('marga', [MargaController::class, 'public'])->name('marga.view');

Route::get('budaya', [BudayaController::class, 'index'])->name('budaya.view');

Route::get('cerita', [StoryController::class, 'publicIndex'])->name('cerita.index');

Route::get('cerita/{story}', [StoryController::class, 'show'])->name('cerita.show');

Route::get('kegiatan', [EventController::class, 'publicIndex'])->name('kegiatan.index');

Route::get('kegiatan/{event}', [EventController::class, 'show'])->name('kegiatan.show');

Route::get('komunitas', [KomunitasController::class, 'index'])->name('komunitas.view');

Route::get('tentang', [TentangController::class, 'index'])->name('tentang.view');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('dashboard/news-feed', [NewsFeedController::class, 'index'])
        ->name('news-feed.index');
    Route::post('dashboard/news-feed/statuses', [FeedPostController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('news-feed.posts.store');
    Route::post('dashboard/news-feed/statuses/{feedPost}/comments', [FeedCommentController::class, 'store'])
        ->middleware('throttle:60,1')
        ->name('news-feed.posts.comments.store');

    Route::get('contacts', [ContactController::class, 'index'])->name('contacts.index');
    Route::get('contacts/{contact}', [ContactController::class, 'show'])->name('contacts.show');
    Route::get('contacts/{contact}/messages', [ContactController::class, 'messages'])
        ->name('contacts.messages.index');
    Route::post('contacts/{contact}/messages', [MessageController::class, 'store'])
        ->middleware('throttle:60,1')
        ->name('contacts.messages.store');

    Route::get('dashboard/tarombo', [TaromboController::class, 'index'])->name('tarombo.index');

    Route::get('dashboard/tarombo/full/{view}', [TaromboController::class, 'fullscreen'])
        ->where('view', 'diagram|tree')
        ->name('tarombo.fullscreen');

    Route::get('people', [PersonController::class, 'index'])->name('people.index');

    Route::get('people/create', [PersonController::class, 'create'])->name('people.create');

    Route::post('people', [PersonController::class, 'store'])->name('people.store');

    Route::get('people/{person}', [PersonController::class, 'show'])->name('people.show');

    Route::get('people/{person}/edit', [PersonController::class, 'edit'])->name('people.edit');

    Route::put('people/{person}', [PersonController::class, 'update'])->name('people.update');
    Route::get('family-trees/{familyTree}', [PersonController::class, 'showFamilyTree'])->name('family-trees.show');
    Route::post('family-trees/{familyTree}/duplicate', [PersonController::class, 'duplicateFamilyTree'])->name('family-trees.duplicate');
    Route::get('family-trees/{familyTree}/edit', [PersonController::class, 'editFamilyTree'])->name('family-trees.edit');
    Route::put('family-trees/{familyTree}', [PersonController::class, 'updateFamilyTree'])->name('family-trees.update');
    Route::delete('family-trees/{familyTree}', [FamilyTreeDeletionController::class, 'destroy'])->name('family-trees.destroy');

    Route::resource('events', EventController::class)->except(['show']);
    Route::resource('stories', StoryController::class)->except(['show']);
});

Route::middleware(['auth', 'role.staff'])->group(function () {
    Route::delete('people/{person}', [PersonController::class, 'destroy'])->name('people.destroy');

    Route::get('people/{person}/preview', [PersonController::class, 'preview'])->name('people.preview');
    Route::get('people/{person}/silsilah', [PersonController::class, 'silsilah'])->name('people.silsilah');
    Route::get('dashboard/marga', [MargaController::class, 'index'])->name('marga.index');
    Route::post('dashboard/marga', [MargaController::class, 'store'])->name('marga.store');
    Route::put('dashboard/marga/{marga}', [MargaController::class, 'update'])->name('marga.update');
    Route::delete('dashboard/marga/{marga}', [MargaController::class, 'destroy'])->name('marga.destroy');

});

Route::middleware(['auth', 'role.admin'])->group(function () {
    Route::resource('sub-admins', SubAdminController::class)->except(['show']);
    Route::post('dashboard/contributions/contributors', [ContributionController::class, 'storeContributor'])->name('contributions.contributors.store');
    Route::delete('dashboard/contributions/contributors/{contributor}', [ContributionController::class, 'destroyContributor'])->name('contributions.contributors.destroy');
});

Route::middleware(['auth', 'role.contributor'])->group(function () {
    Route::get('dashboard/contributions', [ContributionController::class, 'index'])->name('contributions.index');
    Route::post('dashboard/contributions/{contribution}/approve', [ContributionController::class, 'approve'])->name('contributions.approve');
    Route::post('dashboard/contributions/{contribution}/reject', [ContributionController::class, 'reject'])->name('contributions.reject');
    Route::post('events/{event}/approve', [EventController::class, 'approve'])->name('events.approve');
    Route::post('events/{event}/reject', [EventController::class, 'reject'])->name('events.reject');
    Route::post('stories/{story}/approve', [StoryController::class, 'approve'])->name('stories.approve');
    Route::post('stories/{story}/reject', [StoryController::class, 'reject'])->name('stories.reject');
    Route::post('family-tree-deletions/{deletion}/approve', [FamilyTreeDeletionController::class, 'approve'])->name('family-tree-deletions.approve');
    Route::post('family-tree-deletions/{deletion}/reject', [FamilyTreeDeletionController::class, 'reject'])->name('family-tree-deletions.reject');
});

require __DIR__.'/settings.php';

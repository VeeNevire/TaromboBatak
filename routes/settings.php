<?php

use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Settings\TelegramConnectionController;
use App\Http\Controllers\Settings\TelegramMtprotoController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('settings/telegram', [TelegramConnectionController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('telegram-connection.store');
    Route::delete('settings/telegram', [TelegramConnectionController::class, 'destroy'])
        ->name('telegram-connection.destroy');
    Route::get('settings/telegram/mtproto', [TelegramMtprotoController::class, 'index'])->name('telegram-mtproto.index');
    Route::post('settings/telegram/mtproto', [TelegramMtprotoController::class, 'store'])->middleware('throttle:5,3')->name('telegram-mtproto.store');
    Route::post('settings/telegram/mtproto/qr', [TelegramMtprotoController::class, 'qr'])->middleware('throttle:5,3')->name('telegram-mtproto.qr');
    Route::get('settings/telegram/mtproto/qr/status', [TelegramMtprotoController::class, 'qrStatus'])->middleware('throttle:8,1')->name('telegram-mtproto.qr-status');
    Route::post('settings/telegram/mtproto/code', [TelegramMtprotoController::class, 'verifyCode'])->middleware('throttle:5,3')->name('telegram-mtproto.code');
    Route::post('settings/telegram/mtproto/resend', [TelegramMtprotoController::class, 'resendCode'])->middleware('throttle:5,3')->name('telegram-mtproto.resend');
    Route::post('settings/telegram/mtproto/password', [TelegramMtprotoController::class, 'verifyPassword'])->middleware('throttle:5,3')->name('telegram-mtproto.password');
    Route::delete('settings/telegram/mtproto', [TelegramMtprotoController::class, 'destroy'])->name('telegram-mtproto.destroy');
});

Route::middleware(['auth'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');
});

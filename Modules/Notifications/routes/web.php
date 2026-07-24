<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Notifications\Http\Controllers\NotificationsController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/notificacoes', [NotificationsController::class, 'index'])->name('notifications.index');
    Route::patch('/notificacoes/{notification}/lida', [NotificationsController::class, 'markRead'])->name('notifications.read');
    Route::post('/notificacoes/marcar-todas-lidas', [NotificationsController::class, 'markAllRead'])->name('notifications.readAll');
});

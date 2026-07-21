<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Scheduling\Http\Controllers\AgendaController;
use Modules\Scheduling\Http\Controllers\SessionController;
use Modules\Scheduling\Http\Controllers\WaitingListController;

Route::middleware(['auth', 'verified', 'resolve.tenant'])->group(function () {
    Route::get('/agenda', [AgendaController::class, 'index'])->name('scheduling.agenda.index');
    Route::get('/agenda/{psychologist}', [AgendaController::class, 'show'])->name('scheduling.agenda.show');
    Route::post('/agenda/{psychologist}/reservar', [AgendaController::class, 'store'])->name('scheduling.agenda.store');
    Route::post('/agenda/{psychologist}/lista-espera', [WaitingListController::class, 'store'])
        ->name('scheduling.waiting-list.store');

    Route::get('/minhas-sessoes', [SessionController::class, 'mine'])->name('scheduling.my-sessions');
    Route::post('/sessoes/{session}/cancelar', [SessionController::class, 'cancel'])->name('scheduling.sessions.cancel');
    Route::post('/sessoes/{session}/reagendar', [SessionController::class, 'reschedule'])->name('scheduling.sessions.reschedule');
    Route::post('/sessoes/{session}/marcar-realizada', [SessionController::class, 'markRealizada'])
        ->name('scheduling.sessions.mark-realizada');
    Route::post('/sessoes/{session}/marcar-nao-compareceu', [SessionController::class, 'markNaoCompareceu'])
        ->name('scheduling.sessions.mark-nao-compareceu');

    Route::get('/lista-espera', [WaitingListController::class, 'index'])->name('scheduling.waiting-list.index');
});

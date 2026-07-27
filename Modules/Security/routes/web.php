<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Security\Http\Controllers\Lgpd\ConsentController;
use Modules\Security\Http\Controllers\Lgpd\LegalDocumentController;
use Modules\Security\Http\Controllers\Lgpd\PersonalDataController;

/*
 * LGPD (Fase 10). Aceite de documentos legais, acesso aos próprios dados (Art. 18) e
 * gestão das versões dos documentos.
 */
Route::middleware(['auth', 'verified'])->group(function () {
    // Tela de aceite — sem resolve.tenant (o ConsentChecker lê o tenant do próprio
    // usuário); exigir tenant resolvido aqui atrapalharia o gating.
    Route::get('/lgpd/consentimento', [ConsentController::class, 'show'])->name('lgpd.consent.show');
    Route::post('/lgpd/consentimento', [ConsentController::class, 'store'])->name('lgpd.consent.store');
});

Route::middleware(['auth', 'verified', 'resolve.tenant'])->group(function () {
    // Acesso/portabilidade do próprio dado (Art. 18).
    Route::get('/lgpd/meus-dados', [PersonalDataController::class, 'show'])->name('lgpd.my-data.show');
    Route::get('/lgpd/meus-dados/download', [PersonalDataController::class, 'download'])
        ->middleware('throttle:30,1')
        ->name('lgpd.my-data.download');

    // Gestão das versões dos documentos legais (dono da clínica).
    Route::middleware('can:manage-legal')->group(function () {
        Route::get('/lgpd/documentos', [LegalDocumentController::class, 'index'])->name('lgpd.legal-documents.index');
        Route::get('/lgpd/documentos/novo', [LegalDocumentController::class, 'create'])->name('lgpd.legal-documents.create');
        Route::post('/lgpd/documentos', [LegalDocumentController::class, 'store'])->name('lgpd.legal-documents.store');
    });
});

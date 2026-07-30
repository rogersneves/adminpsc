<?php

use App\Http\Controllers\ApiTokenController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
});

// Tokens de API pessoais (marco: API pública REST). Emitidos na sessão web
// autenticada — a API não tem endpoint de login (não contorna o MFA obrigatório).
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/api-tokens', [ApiTokenController::class, 'index'])->name('api-tokens.index');
    Route::post('/api-tokens', [ApiTokenController::class, 'store'])->name('api-tokens.store');
    Route::delete('/api-tokens/{tokenId}', [ApiTokenController::class, 'destroy'])->name('api-tokens.destroy');
});

// GET /dashboard agora é registrada por Modules\Reports\Http\Controllers\DashboardController
// (Fase 6) — não redeclarar aqui, senão esta closure vence por ordem de registro.

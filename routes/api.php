<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\ChargeController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\PsychologistController;
use App\Http\Controllers\Api\V1\SessionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API pública v1 (marco: API pública REST)
|--------------------------------------------------------------------------
|
| Autenticação por token Bearer (Sanctum, guard `sanctum`). Cada endpoint reusa
| as MESMAS Actions/queries dos controllers Inertia — o desacoplamento
| Actions/Services↔HTTP feito desde a Fase 0 (docs/01-Arquitetura.md) é o que
| permite isso sem duplicar regra de negócio. `resolve.tenant` resolve o tenant do
| dono do token (ResolveTenant usa $request->user(), agnóstico de guard).
|
| Tokens são emitidos pelo próprio usuário na área autenticada web (/api-tokens),
| não por um endpoint de login — assim o fluxo de MFA obrigatório do app não é
| contornado pela API.
*/
Route::middleware(['auth:sanctum', 'throttle:60,1', 'resolve.tenant'])
    ->prefix('v1')
    ->name('api.v1.')
    ->group(function () {
        Route::get('/me', MeController::class)->name('me');

        Route::get('/psychologists', [PsychologistController::class, 'index'])->name('psychologists.index');

        Route::get('/sessions', [SessionController::class, 'index'])->name('sessions.index');
        Route::post('/sessions', [SessionController::class, 'store'])->name('sessions.store');

        Route::get('/charges', [ChargeController::class, 'index'])->name('charges.index');
    });

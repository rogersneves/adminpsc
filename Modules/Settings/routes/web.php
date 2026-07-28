<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Settings\Http\Controllers\PlatformTenantController;
use Modules\Settings\Http\Controllers\SecretaryController;
use Modules\Settings\Http\Controllers\SettingsController;
use Modules\Settings\Http\Controllers\UnitController;

/*
 * Configurações da clínica (Fase 11) — dono da clínica, tenant resolvido.
 */
Route::middleware(['auth', 'verified', 'resolve.tenant', 'can:manage-clinic-settings'])->group(function () {
    Route::get('/configuracoes', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('/configuracoes', [SettingsController::class, 'update'])->name('settings.update');

    // Unidades (filiais) — estrutura organizacional da clínica.
    Route::get('/unidades', [UnitController::class, 'index'])->name('units.index');
    Route::post('/unidades', [UnitController::class, 'store'])->name('units.store');
    Route::put('/unidades/{unit}', [UnitController::class, 'update'])->name('units.update');
    Route::delete('/unidades/{unit}', [UnitController::class, 'destroy'])->name('units.destroy');
});

// Convite/gestão de secretárias (mesma permissão do cadastro de psicólogo).
Route::middleware(['auth', 'verified', 'resolve.tenant', 'can:manage-users'])->group(function () {
    Route::get('/secretarias', [SecretaryController::class, 'index'])->name('secretaries.index');
    Route::post('/secretarias', [SecretaryController::class, 'store'])->name('secretaries.store');
});

/*
 * Gestão de tenants pela plataforma (Super Admin) — SEM resolve.tenant (opera
 * cross-tenant, o Super Admin não tem tenant próprio).
 */
Route::middleware(['auth', 'verified', 'can:platform.manage-tenants'])
    ->prefix('plataforma/tenants')
    ->name('platform.tenants.')
    ->group(function () {
        Route::get('/', [PlatformTenantController::class, 'index'])->name('index');
        Route::post('/', [PlatformTenantController::class, 'store'])->name('store');
        Route::put('/{tenant}', [PlatformTenantController::class, 'update'])->name('update');
    });

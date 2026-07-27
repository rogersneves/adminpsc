<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Settings\Http\Controllers\PlatformTenantController;
use Modules\Settings\Http\Controllers\SettingsController;

/*
 * Configurações da clínica (Fase 11) — dono da clínica, tenant resolvido.
 */
Route::middleware(['auth', 'verified', 'resolve.tenant', 'can:manage-clinic-settings'])->group(function () {
    Route::get('/configuracoes', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('/configuracoes', [SettingsController::class, 'update'])->name('settings.update');
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

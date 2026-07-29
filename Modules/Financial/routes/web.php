<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Financial\Http\Controllers\FinancialChargeController;
use Modules\Financial\Http\Controllers\FinancialLedgerController;
use Modules\Financial\Http\Controllers\FinancialPatientsController;
use Modules\Financial\Http\Controllers\HealthPlanController;

// Convênios (marco) — catálogo de faturamento, restrito a manage-financial.
Route::middleware(['auth', 'verified', 'resolve.tenant', 'can:manage-financial'])->group(function () {
    Route::get('/convenios', [HealthPlanController::class, 'index'])->name('health-plans.index');
    Route::post('/convenios', [HealthPlanController::class, 'store'])->name('health-plans.store');
    Route::put('/convenios/{healthPlan}', [HealthPlanController::class, 'update'])->name('health-plans.update');
    Route::delete('/convenios/{healthPlan}', [HealthPlanController::class, 'destroy'])->name('health-plans.destroy');
});

Route::middleware(['auth', 'verified', 'resolve.tenant'])->group(function () {
    Route::get('/financeiro/pacientes', [FinancialPatientsController::class, 'index'])->name('financial.patients');
    Route::get('/pacientes/{patient}/financeiro', [FinancialLedgerController::class, 'show'])->name('financial.ledger');

    Route::post('/pacientes/{patient}/financeiro/cobrancas', [FinancialChargeController::class, 'store'])->name('financial.charges.store');
    Route::put('/financeiro/cobrancas/{charge}', [FinancialChargeController::class, 'update'])->name('financial.charges.update');
    Route::delete('/financeiro/cobrancas/{charge}', [FinancialChargeController::class, 'destroy'])->name('financial.charges.destroy');
});

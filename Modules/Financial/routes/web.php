<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Financial\Http\Controllers\FinancialChargeController;
use Modules\Financial\Http\Controllers\FinancialLedgerController;
use Modules\Financial\Http\Controllers\FinancialPatientsController;

Route::middleware(['auth', 'verified', 'resolve.tenant'])->group(function () {
    Route::get('/financeiro/pacientes', [FinancialPatientsController::class, 'index'])->name('financial.patients');
    Route::get('/pacientes/{patient}/financeiro', [FinancialLedgerController::class, 'show'])->name('financial.ledger');

    Route::post('/pacientes/{patient}/financeiro/cobrancas', [FinancialChargeController::class, 'store'])->name('financial.charges.store');
    Route::put('/financeiro/cobrancas/{charge}', [FinancialChargeController::class, 'update'])->name('financial.charges.update');
    Route::delete('/financeiro/cobrancas/{charge}', [FinancialChargeController::class, 'destroy'])->name('financial.charges.destroy');
});

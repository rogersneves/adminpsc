<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Payments\Http\Controllers\PaymentController;
use Modules\Payments\Http\Controllers\PaymentReceiptController;
use Modules\Payments\Http\Controllers\PaymentReversalController;

Route::middleware(['auth', 'verified', 'resolve.tenant'])->group(function () {
    Route::post('/financeiro/cobrancas/{charge}/pagamentos', [PaymentController::class, 'store'])->name('payments.store');
    Route::post('/financeiro/pagamentos/{payment}/estornar', [PaymentReversalController::class, 'store'])->name('payments.reverse');
    Route::get('/financeiro/pagamentos/{payment}/recibo', [PaymentReceiptController::class, 'download'])->name('payments.receipt');
});

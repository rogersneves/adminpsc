<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Payments\Http\Controllers\GatewayChargeController;
use Modules\Payments\Http\Controllers\PaymentController;
use Modules\Payments\Http\Controllers\PaymentReceiptController;
use Modules\Payments\Http\Controllers\PaymentReversalController;
use Modules\Payments\Http\Controllers\WebhookController;

// Webhook do gateway (marco): público (provedor chama sem sessão), isento de CSRF
// (bootstrap/app.php). A verificação de origem é por driver (verifyWebhook).
Route::post('/webhooks/payments/{driver}', [WebhookController::class, 'handle'])
    ->middleware('throttle:120,1')
    ->name('payments.webhook');

Route::middleware(['auth', 'verified', 'resolve.tenant'])->group(function () {
    Route::post('/financeiro/cobrancas/{charge}/pagamentos', [PaymentController::class, 'store'])->name('payments.store');
    // Emite a cobrança no gateway (link/PIX) para uma cobrança existente.
    Route::post('/financeiro/cobrancas/{charge}/gateway', [GatewayChargeController::class, 'store'])->name('payments.gateway');
    Route::post('/financeiro/pagamentos/{payment}/estornar', [PaymentReversalController::class, 'store'])->name('payments.reverse');
    // Download de recibo em PDF: endpoint de exportação, rate-limited (Fase 9).
    Route::get('/financeiro/pagamentos/{payment}/recibo', [PaymentReceiptController::class, 'download'])
        ->middleware('throttle:30,1')
        ->name('payments.receipt');
});

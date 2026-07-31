<?php

declare(strict_types=1);

namespace Modules\Payments\Contracts;

use Illuminate\Http\Request;
use Modules\Financial\Models\FinancialCharge;
use Modules\Payments\DTOs\GatewayChargeResult;
use Modules\Payments\DTOs\GatewayWebhookEvent;
use Modules\Payments\Enums\PaymentMethod;

/**
 * Contrato de um gateway de pagamento (marco: gateways reais + PIX). Cada provedor
 * (Asaas, Mercado Pago, Pagar.me...) é um driver que implementa esta interface; o
 * driver ativo vem de config('payments.default') via PaymentGatewayManager.
 *
 * Fluxo: a cobrança já existe no sistema (FinancialCharge, Fase 5) →
 * `createCharge()` cria a cobrança correspondente no provedor e devolve o link/PIX →
 * o cliente paga → o provedor chama o webhook → `verifyWebhook()`/`parseWebhook()`
 * traduzem, e a conciliação registra o Payment reusando a RecordPaymentAction (Fase 5).
 *
 * O contrato é charge-centric (não Payment-centric): o Payment só nasce depois da
 * confirmação — não existe Payment para "cobrar".
 */
interface PaymentGatewayInterface
{
    public function name(): string;

    /**
     * Cria a cobrança no provedor para uma FinancialCharge e devolve id/status/link/PIX.
     */
    public function createCharge(FinancialCharge $charge, PaymentMethod $method): GatewayChargeResult;

    /**
     * Verifica a autenticidade do webhook (assinatura/token). `false` → 403.
     */
    public function verifyWebhook(Request $request): bool;

    /**
     * Normaliza o payload do webhook; `null` quando o evento é irrelevante.
     */
    public function parseWebhook(Request $request): ?GatewayWebhookEvent;
}

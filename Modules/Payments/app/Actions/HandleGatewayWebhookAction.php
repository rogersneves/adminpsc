<?php

declare(strict_types=1);

namespace Modules\Payments\Actions;

use Carbon\CarbonImmutable;
use Modules\Financial\Models\FinancialCharge;
use Modules\Payments\DTOs\GatewayWebhookEvent;
use Modules\Payments\Models\Payment;
use Modules\Tenant\Support\CurrentTenant;

/**
 * Concilia um evento de pagamento vindo do webhook do gateway (marco). Roda em
 * contexto de convidado (o provedor chama sem sessão), então:
 *
 * - acha a cobrança por `gateway_charge_id` com `withoutTenantScope()` (não há
 *   CurrentTenant resolvido) e então FIXA o CurrentTenant a partir da própria
 *   cobrança, pra que a RecordPaymentAction (que faz queries tenant-scoped com
 *   lockForUpdate) funcione;
 * - é IDEMPOTENTE: reenvios do webhook (Asaas manda PAYMENT_RECEIVED e
 *   PAYMENT_CONFIRMED) não duplicam pagamento — dedupe por Payment.gateway_reference.
 *
 * Ao registrar o pagamento reusa a RecordPaymentAction (Fase 5) — logo dispara
 * PaymentWasRecorded, e Notifications/Audit reagem de graça (Fases 7/9).
 */
class HandleGatewayWebhookAction
{
    public function __construct(
        private readonly RecordPaymentAction $recordPayment,
        private readonly CurrentTenant $currentTenant,
    ) {}

    public function __invoke(GatewayWebhookEvent $event): ?Payment
    {
        if (! $event->isPaid()) {
            return null;
        }

        $charge = FinancialCharge::query()
            ->withoutTenantScope()
            ->where('gateway_charge_id', $event->gatewayChargeId)
            ->first();

        if ($charge === null) {
            return null; // cobrança de outro sistema / desconhecida
        }

        // Estabelece o contexto de tenant a partir da cobrança conciliada.
        $this->currentTenant->set($charge->tenant);

        // Idempotência: já registramos este pagamento do gateway?
        $already = Payment::query()
            ->where('charge_id', $charge->id)
            ->where('gateway_reference', $event->externalId)
            ->exists();

        if ($already) {
            return null;
        }

        return ($this->recordPayment)(
            $charge,
            $event->amount,
            $event->method,
            CarbonImmutable::now(),
            $event->externalId,
        );
    }
}

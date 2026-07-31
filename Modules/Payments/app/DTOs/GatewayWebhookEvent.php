<?php

declare(strict_types=1);

namespace Modules\Payments\DTOs;

use Modules\Payments\Enums\PaymentMethod;

/**
 * Evento normalizado de webhook do gateway. Cada driver traduz o payload cru do
 * provedor para esta forma. `paid` indica confirmação de pagamento; qualquer outro
 * tipo é ignorado pela conciliação. `externalId` (id do pagamento no provedor) é a
 * chave de idempotência — guardado em Payment.gateway_reference para deduplicar
 * reenvios de webhook.
 */
final readonly class GatewayWebhookEvent
{
    public function __construct(
        public string $type,
        public string $gatewayChargeId,
        public string $externalId,
        public float $amount,
        public PaymentMethod $method,
    ) {}

    public function isPaid(): bool
    {
        return $this->type === 'paid';
    }
}

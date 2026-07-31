<?php

declare(strict_types=1);

namespace Modules\Payments\DTOs;

/**
 * Resultado da criação de uma cobrança no gateway. `paymentUrl` é o link de pagamento
 * (fatura hospedada); `pixPayload` é o copia-e-cola do PIX quando aplicável. Ambos
 * podem ser nulos (ex.: driver `null`).
 */
final readonly class GatewayChargeResult
{
    public function __construct(
        public string $gatewayChargeId,
        public string $status,
        public ?string $paymentUrl = null,
        public ?string $pixPayload = null,
    ) {}
}

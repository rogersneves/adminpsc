<?php

declare(strict_types=1);

namespace Modules\Payments\Gateways;

use Illuminate\Http\Request;
use Modules\Financial\Models\FinancialCharge;
use Modules\Payments\Contracts\PaymentGatewayInterface;
use Modules\Payments\DTOs\GatewayChargeResult;
use Modules\Payments\DTOs\GatewayWebhookEvent;
use Modules\Payments\Enums\PaymentMethod;

/**
 * Driver padrão: nenhum provedor externo. Marca a cobrança como aguardando pagamento
 * (sem link/PIX) — a staff continua registrando o pagamento manualmente pela
 * RecordPaymentAction, exatamente como antes deste marco. Também é o driver usado nos
 * testes que não querem tocar a rede.
 */
class NullGateway implements PaymentGatewayInterface
{
    public function name(): string
    {
        return 'null';
    }

    public function createCharge(FinancialCharge $charge, PaymentMethod $method): GatewayChargeResult
    {
        return new GatewayChargeResult(
            gatewayChargeId: 'null_'.$charge->id,
            status: 'pending',
        );
    }

    public function verifyWebhook(Request $request): bool
    {
        return false;
    }

    public function parseWebhook(Request $request): ?GatewayWebhookEvent
    {
        return null;
    }
}

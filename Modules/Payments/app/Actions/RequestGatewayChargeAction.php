<?php

declare(strict_types=1);

namespace Modules\Payments\Actions;

use Modules\Financial\Models\FinancialCharge;
use Modules\Payments\Contracts\PaymentGatewayInterface;
use Modules\Payments\Enums\PaymentMethod;

/**
 * Cria a cobrança no gateway ativo para uma FinancialCharge existente (marco:
 * gateways reais + PIX) e guarda o id/status/link/PIX na própria cobrança. O
 * pagamento em si só é registrado depois, pela conciliação do webhook.
 */
class RequestGatewayChargeAction
{
    public function __construct(private readonly PaymentGatewayInterface $gateway) {}

    public function __invoke(FinancialCharge $charge, PaymentMethod $method): FinancialCharge
    {
        $result = $this->gateway->createCharge($charge, $method);

        $charge->update([
            'gateway' => $this->gateway->name(),
            'gateway_charge_id' => $result->gatewayChargeId,
            'gateway_status' => $result->status,
            'payment_url' => $result->paymentUrl,
            'pix_payload' => $result->pixPayload,
        ]);

        return $charge->refresh();
    }
}

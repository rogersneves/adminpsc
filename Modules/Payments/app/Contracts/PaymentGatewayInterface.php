<?php

declare(strict_types=1);

namespace Modules\Payments\Contracts;

use Modules\Payments\Models\Payment;

/**
 * Puramente arquitetural nesta fase (docs/06-Roadmap.md Fase 5: "arquitetura
 * preparada para gateways/PIX ... sem integração real ainda"). Não tem nenhuma
 * implementação nem binding registrado no container — todo pagamento hoje
 * (inclusive `pix`, ver Modules\Payments\Enums\PaymentMethod) é registrado
 * manualmente pela staff via RecordPaymentAction, sem chamar gateway nenhum.
 */
interface PaymentGatewayInterface
{
    public function charge(Payment $payment): bool;

    public function refund(Payment $payment): bool;
}

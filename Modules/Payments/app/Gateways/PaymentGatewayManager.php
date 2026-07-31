<?php

declare(strict_types=1);

namespace Modules\Payments\Gateways;

use Illuminate\Support\Manager;
use Modules\Payments\Contracts\PaymentGatewayInterface;

/**
 * Resolve o driver de gateway ativo (config('payments.default')). Padrão Manager do
 * Laravel: cada `create{Driver}Driver()` devolve uma implementação de
 * PaymentGatewayInterface; adicionar um provedor é adicionar um método aqui + a
 * classe do driver, sem tocar em quem consome o contrato.
 */
class PaymentGatewayManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return (string) $this->config->get('payments.default', 'null');
    }

    public function createNullDriver(): PaymentGatewayInterface
    {
        return new NullGateway;
    }

    public function createAsaasDriver(): PaymentGatewayInterface
    {
        return new AsaasGateway($this->config->get('payments.gateways.asaas', []));
    }
}

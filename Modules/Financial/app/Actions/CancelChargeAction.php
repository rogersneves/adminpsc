<?php

declare(strict_types=1);

namespace Modules\Financial\Actions;

use Modules\Financial\Enums\ChargeStatus;
use Modules\Financial\Exceptions\ChargeHasPaymentException;
use Modules\Financial\Models\FinancialCharge;

class CancelChargeAction
{
    public function __invoke(FinancialCharge $charge): FinancialCharge
    {
        if ($charge->payments()->whereNull('reversed_at')->exists()) {
            throw new ChargeHasPaymentException(
                'Não é possível cancelar uma cobrança que já tem pagamento — estorne o pagamento primeiro.',
            );
        }

        $charge->update(['status' => ChargeStatus::Cancelado]);

        return $charge->refresh();
    }
}

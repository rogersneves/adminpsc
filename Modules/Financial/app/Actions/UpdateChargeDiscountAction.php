<?php

declare(strict_types=1);

namespace Modules\Financial\Actions;

use Modules\Financial\Exceptions\ChargeHasPaymentException;
use Modules\Financial\Models\FinancialCharge;

class UpdateChargeDiscountAction
{
    public function __invoke(FinancialCharge $charge, float $discountAmount): FinancialCharge
    {
        if ($charge->payments()->whereNull('reversed_at')->exists()) {
            throw new ChargeHasPaymentException(
                'Não é possível alterar o desconto de uma cobrança que já tem pagamento registrado.',
            );
        }

        $charge->update(['discount_amount' => $discountAmount]);

        return $charge->refresh();
    }
}

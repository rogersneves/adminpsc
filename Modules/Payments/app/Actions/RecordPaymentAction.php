<?php

declare(strict_types=1);

namespace Modules\Payments\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Financial\Models\FinancialCharge;
use Modules\Financial\Services\ChargeStatusCalculator;
use Modules\Payments\Enums\PaymentMethod;
use Modules\Payments\Models\Payment;

/**
 * Mesmo padrão de BookSessionAction (Fase 3): trava a linha pai (FinancialCharge)
 * com lockForUpdate() antes de mutar, dentro de uma transação, para serializar
 * pagamentos concorrentes contra a mesma cobrança.
 */
class RecordPaymentAction
{
    public function __construct(private readonly ChargeStatusCalculator $calculator) {}

    public function __invoke(
        FinancialCharge $charge,
        float $amount,
        PaymentMethod $method,
        CarbonImmutable $paidAt,
        ?string $gatewayReference = null,
    ): Payment {
        return DB::transaction(function () use ($charge, $amount, $method, $paidAt, $gatewayReference) {
            $lockedCharge = FinancialCharge::query()
                ->whereKey($charge->id)
                ->lockForUpdate()
                ->firstOrFail();

            $payment = Payment::query()->create([
                'tenant_id' => $lockedCharge->tenant_id,
                'charge_id' => $lockedCharge->id,
                'amount' => $amount,
                'paid_at' => $paidAt,
                'method' => $method,
                'gateway_reference' => $gatewayReference,
            ]);

            $lockedCharge->update(['status' => $this->calculator->recalculate($lockedCharge)]);

            return $payment;
        });
    }
}

<?php

declare(strict_types=1);

namespace Modules\Payments\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Financial\Models\FinancialCharge;
use Modules\Financial\Services\ChargeStatusCalculator;
use Modules\Payments\Models\Payment;

class ReversePaymentAction
{
    public function __construct(private readonly ChargeStatusCalculator $calculator) {}

    public function __invoke(Payment $payment): Payment
    {
        return DB::transaction(function () use ($payment) {
            $lockedCharge = FinancialCharge::query()
                ->whereKey($payment->charge_id)
                ->lockForUpdate()
                ->firstOrFail();

            $payment->update(['reversed_at' => now()]);

            $lockedCharge->update(['status' => $this->calculator->recalculate($lockedCharge)]);

            return $payment->refresh();
        });
    }
}

<?php

declare(strict_types=1);

namespace Modules\Reports\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Modules\Financial\Enums\ChargeStatus;
use Modules\Financial\Models\FinancialCharge;

class BuildFinancialReportAction
{
    /**
     * @param  list<string>|null  $patientIds  null = sem filtro de book (admin vendo tudo)
     */
    public function __invoke(
        ?array $patientIds,
        ?string $patientId = null,
        ?ChargeStatus $status = null,
        ?CarbonImmutable $from = null,
        ?CarbonImmutable $to = null,
    ): Collection {
        $query = FinancialCharge::query()->with('patient');

        if ($patientIds !== null) {
            $query->whereIn('patient_id', $patientIds);
        }

        if ($patientId !== null) {
            $query->where('patient_id', $patientId);
        }

        if ($status !== null) {
            $query->where('status', $status->value);
        }

        if ($from !== null) {
            $query->where('due_date', '>=', $from);
        }

        if ($to !== null) {
            $query->where('due_date', '<=', $to);
        }

        return $query->orderBy('due_date')
            ->get()
            ->map(fn (FinancialCharge $charge) => [
                'patient_name' => $charge->patient->display_name,
                'amount' => (float) $charge->amount,
                'discount_amount' => (float) $charge->discount_amount,
                'fine_amount' => (float) $charge->fine_amount,
                'interest_amount' => (float) $charge->interest_amount,
                'total_due' => $charge->totalDue(),
                'total_paid' => $charge->totalPaid(),
                'due_date' => $charge->due_date->toDateString(),
                'status' => $charge->status->value,
                'installment_number' => $charge->installment_number,
                'installment_total' => $charge->installment_total,
            ]);
    }
}

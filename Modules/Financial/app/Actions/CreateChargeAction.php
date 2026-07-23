<?php

declare(strict_types=1);

namespace Modules\Financial\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Financial\Enums\ChargeStatus;
use Modules\Financial\Models\FinancialCharge;
use Modules\Patients\Models\Patient;
use Modules\Scheduling\Models\Session;

/**
 * Parcelamento gera N linhas independentes (não existe tabela de "grupo de
 * parcelamento" no schema documentado — installment_number/installment_total já
 * descrevem a posição, ex. "2 de 4"). Valor e desconto são divididos igualmente entre
 * as parcelas; a última parcela absorve o resto da divisão em centavos pra não perder
 * nem sobrar dinheiro por arredondamento. Vencimentos espaçados por 1 mês a partir da
 * data informada.
 */
class CreateChargeAction
{
    public function __invoke(
        Patient $patient,
        float $amount,
        CarbonImmutable $dueDate,
        int $installmentTotal = 1,
        float $discountAmount = 0.0,
        ?Session $session = null,
    ): Collection {
        return DB::transaction(function () use ($patient, $amount, $dueDate, $installmentTotal, $discountAmount, $session) {
            $amounts = $this->split($amount, $installmentTotal);
            $discounts = $this->split($discountAmount, $installmentTotal);

            $charges = collect();

            for ($i = 0; $i < $installmentTotal; $i++) {
                $charges->push(FinancialCharge::query()->create([
                    'tenant_id' => $patient->tenant_id,
                    'patient_id' => $patient->id,
                    'session_id' => $session?->id,
                    'amount' => $amounts[$i],
                    'discount_amount' => $discounts[$i],
                    'due_date' => $dueDate->addMonthsNoOverflow($i),
                    'status' => ChargeStatus::EmAberto,
                    'installment_number' => $i + 1,
                    'installment_total' => $installmentTotal,
                ]));
            }

            return $charges;
        });
    }

    /**
     * @return list<float>
     */
    private function split(float $total, int $parts): array
    {
        $totalCents = (int) round($total * 100);
        $baseCents = intdiv($totalCents, $parts);
        $remainderCents = $totalCents - ($baseCents * $parts);

        $amounts = array_fill(0, $parts, $baseCents);
        $amounts[$parts - 1] += $remainderCents;

        return array_map(static fn (int $cents): float => $cents / 100, $amounts);
    }
}

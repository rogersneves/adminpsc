<?php

declare(strict_types=1);

namespace Modules\Financial\Console\Commands;

use Illuminate\Console\Command;
use Modules\Financial\Enums\ChargeStatus;
use Modules\Financial\Models\FinancialCharge;

/**
 * Recalcula (não acumula) multa/juros a cada execução, a partir dos dias em atraso
 * na data corrente — por isso é seguro rodar mais de uma vez por dia. Não toca
 * cobranças `parcial` (já têm pagamento, o status delas não depende de due_date —
 * ver Modules\Financial\Services\ChargeStatusCalculator) nem `pago`/`cancelado`/
 * `estornado`.
 */
class ApplyLateChargeFees extends Command
{
    protected $signature = 'financial:apply-late-fees';

    protected $description = 'Recalcula multa/juros e marca como vencidas as cobranças em aberto com due_date no passado';

    public function handle(): int
    {
        $finePercent = (float) config('financial.late_fine_percent');
        $interestPercentPerMonth = (float) config('financial.late_interest_percent_per_month');

        $charges = FinancialCharge::query()
            ->whereIn('status', [ChargeStatus::EmAberto->value, ChargeStatus::Vencido->value])
            ->whereDate('due_date', '<', today())
            ->get();

        foreach ($charges as $charge) {
            $daysLate = (int) $charge->due_date->diffInDays(today(), absolute: true);

            $fineAmount = round((float) $charge->amount * $finePercent / 100, 2);
            $interestAmount = round((float) $charge->amount * ($interestPercentPerMonth / 100 / 30) * $daysLate, 2);

            $charge->update([
                'fine_amount' => $fineAmount,
                'interest_amount' => $interestAmount,
                'status' => ChargeStatus::Vencido,
            ]);
        }

        $this->info("Cobranças vencidas recalculadas: {$charges->count()}");

        return self::SUCCESS;
    }
}

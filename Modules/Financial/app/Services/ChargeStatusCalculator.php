<?php

declare(strict_types=1);

namespace Modules\Financial\Services;

use Modules\Financial\Enums\ChargeStatus;
use Modules\Financial\Models\FinancialCharge;

/**
 * O status de uma cobrança nunca é a fonte de verdade isolada — é sempre recomputado
 * a partir dos pagamentos não estornados. `cancelado` é estado terminal (nunca
 * recalculado por cima). `estornado` distingue "teve pagamento e foi estornado" de
 * `em_aberto`/`vencido` ("nunca teve pagamento nenhum").
 */
class ChargeStatusCalculator
{
    public function recalculate(FinancialCharge $charge): ChargeStatus
    {
        if ($charge->status === ChargeStatus::Cancelado) {
            return ChargeStatus::Cancelado;
        }

        $totalDue = $charge->totalDue();
        $totalPaid = $charge->totalPaid();

        if ($totalPaid >= $totalDue) {
            return ChargeStatus::Pago;
        }

        if ($totalPaid > 0) {
            return ChargeStatus::Parcial;
        }

        if ($charge->payments()->exists()) {
            return ChargeStatus::Estornado;
        }

        return $charge->due_date->lt(today()) ? ChargeStatus::Vencido : ChargeStatus::EmAberto;
    }
}

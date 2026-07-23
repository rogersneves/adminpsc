<?php

declare(strict_types=1);

namespace Modules\Financial\Policies;

use Modules\Patients\Models\Patient;
use Modules\Psychologists\Models\Psychologist;
use Modules\Scheduling\Models\Session;
use Modules\Users\Models\User;

/**
 * Não é Gate::policy — a decisão é sobre "este ator pode ver/gerenciar o financeiro
 * deste Patient", não sobre uma instância única de FinancialCharge (mesmo padrão de
 * MedicalRecordPolicy, Fase 4). `view` inclui o psicólogo que já tratou o paciente
 * (leitura); `manage` (criar cobrança, registrar/estornar pagamento, cancelar) é
 * restrito a quem tem a permissão `manage-financial`.
 */
class FinancialPolicy
{
    public function view(User $actor, Patient $patient): bool
    {
        if ($this->manage($actor, $patient)) {
            return true;
        }

        $psychologist = Psychologist::query()->where('user_id', $actor->id)->first();

        if ($psychologist === null) {
            return false;
        }

        return Session::query()
            ->where('patient_id', $patient->id)
            ->where('psychologist_id', $psychologist->id)
            ->exists();
    }

    public function manage(User $actor, Patient $patient): bool
    {
        if ($actor->hasRole('super_admin')) {
            return true;
        }

        return $actor->tenant_id !== null
            && $actor->tenant_id === $patient->tenant_id
            && $actor->hasPermissionTo('manage-financial');
    }
}

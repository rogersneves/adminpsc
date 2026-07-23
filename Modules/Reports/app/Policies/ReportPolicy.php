<?php

declare(strict_types=1);

namespace Modules\Reports\Policies;

use Modules\Users\Models\User;

/**
 * Sem `$patient` — a autorização aqui é só "este ator pode abrir a seção de
 * relatórios", não sobre um registro específico (o filtro por paciente/psicólogo
 * dentro dos relatórios é aplicado depois, via PsychologistPatientScope). Registrada
 * via Gate::define, mesmo padrão de MedicalRecordPolicy/FinancialPolicy.
 */
class ReportPolicy
{
    public function view(User $actor): bool
    {
        if ($actor->hasRole('super_admin')) {
            return true;
        }

        if ($actor->tenant_id !== null && $actor->hasPermissionTo('manage-users')) {
            return true;
        }

        return $actor->hasRole('psicologo');
    }
}

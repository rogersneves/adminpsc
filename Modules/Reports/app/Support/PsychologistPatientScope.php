<?php

declare(strict_types=1);

namespace Modules\Reports\Support;

use Modules\Psychologists\Models\Psychologist;
use Modules\Scheduling\Models\Session;
use Modules\Users\Models\User;

/**
 * Mesma derivação de "book de pacientes" já usada em MedicalRecordPolicy/FinancialPolicy
 * (Fases 4/5) — quem já teve uma Session com o paciente. Sem tabela de atribuição.
 */
class PsychologistPatientScope
{
    /**
     * @return list<string>|null null = sem filtro (admin vendo todo o tenant)
     */
    public function patientIdsFor(User $actor, ?string $psychologistIdFilter): ?array
    {
        if ($actor->hasRole('super_admin') || $actor->hasPermissionTo('manage-users')) {
            if ($psychologistIdFilter === null) {
                return null;
            }

            return Session::query()
                ->where('psychologist_id', $psychologistIdFilter)
                ->distinct()
                ->pluck('patient_id')
                ->all();
        }

        $psychologist = Psychologist::query()->where('user_id', $actor->id)->first();

        if ($psychologist === null) {
            return [];
        }

        return Session::query()
            ->where('psychologist_id', $psychologist->id)
            ->distinct()
            ->pluck('patient_id')
            ->all();
    }
}

<?php

declare(strict_types=1);

namespace Modules\MedicalRecords\Policies;

use Modules\Patients\Models\Patient;
use Modules\Psychologists\Models\Psychologist;
use Modules\Scheduling\Models\Session;
use Modules\Users\Models\User;

/**
 * Não é uma Policy de instância de MedicalRecordEntry — é sobre a relação
 * "este ator pode acessar o prontuário DESTE paciente" (docs/06-Roadmap.md Fase 4,
 * decisão de escopo #2). Registrada via Gate::define, não por auto-descoberta.
 */
class MedicalRecordPolicy
{
    public function view(User $actor, Patient $patient): bool
    {
        return $this->hasAccess($actor, $patient);
    }

    public function create(User $actor, Patient $patient): bool
    {
        return $this->hasAccess($actor, $patient);
    }

    private function hasAccess(User $actor, Patient $patient): bool
    {
        if ($actor->hasRole('super_admin')) {
            return true;
        }

        if ($actor->tenant_id !== null && $actor->tenant_id === $patient->tenant_id && $actor->hasPermissionTo('manage-users')) {
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
}

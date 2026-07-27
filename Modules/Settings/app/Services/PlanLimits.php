<?php

declare(strict_types=1);

namespace Modules\Settings\Services;

use Modules\Patients\Models\Patient;
use Modules\Psychologists\Models\Psychologist;
use Modules\Settings\Exceptions\PlanLimitReachedException;
use Modules\Tenant\Models\Tenant;

/**
 * Aplicação dos limites do plano do tenant (config/plans.php, Fase 11). Limite
 * `null` = ilimitado. As contagens usam `withoutTenantScope` + `where('tenant_id')`
 * explícito porque a checagem roda dentro de uma Action que pode ou não ter o
 * CurrentTenant resolvido (ex.: seeds/console), e o tenant vem por parâmetro.
 */
class PlanLimits
{
    public function psychologistLimit(Tenant $tenant): ?int
    {
        return $tenant->planConfig()['max_psychologists'] ?? null;
    }

    public function patientLimit(Tenant $tenant): ?int
    {
        return $tenant->planConfig()['max_patients'] ?? null;
    }

    public function canAddPsychologist(Tenant $tenant): bool
    {
        $limit = $this->psychologistLimit($tenant);

        if ($limit === null) {
            return true;
        }

        return $this->countPsychologists($tenant) < $limit;
    }

    public function assertCanAddPsychologist(Tenant $tenant): void
    {
        if (! $this->canAddPsychologist($tenant)) {
            $limit = $this->psychologistLimit($tenant);

            throw new PlanLimitReachedException(
                "O plano atual permite no máximo {$limit} psicólogo(s). Faça upgrade para adicionar mais."
            );
        }
    }

    private function countPsychologists(Tenant $tenant): int
    {
        return Psychologist::query()
            ->withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->count();
    }

    public function countPatients(Tenant $tenant): int
    {
        return Patient::query()
            ->withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->count();
    }
}

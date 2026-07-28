<?php

declare(strict_types=1);

namespace Modules\Settings\Services;

use Illuminate\Support\Collection;
use Modules\Settings\Models\Unit;
use Modules\Users\Models\User;

/**
 * Escopo de unidade de um usuário (marco: secretárias com escopo próprio).
 *
 * `admin_clinica`/`super_admin` (quem tem `manage-users`) enxergam todas as unidades
 * do tenant → retorna `null` (= sem restrição). Uma secretária enxerga apenas as
 * unidades a que está lotada (pivot unit_user). O escopo é aplicado por consulta, não
 * por permissão — a permissão `manage-scheduling` diz "pode gerenciar agenda"; este
 * serviço diz "de quais unidades".
 */
class UnitScope
{
    /**
     * IDs de unidade que o usuário pode gerenciar, ou `null` para "todas" (admin).
     *
     * @return array<int, string>|null
     */
    public function unitIdsFor(User $user): ?array
    {
        if ($user->can('manage-users')) {
            return null;
        }

        return $user->units()->pluck('units.id')->all();
    }

    /**
     * Todas as unidades ativas do tenant do usuário (para seletores/telas de admin).
     *
     * @return Collection<int, Unit>
     */
    public function tenantUnits(User $user)
    {
        return Unit::query()
            ->withoutTenantScope()
            ->where('tenant_id', $user->tenant_id)
            ->orderBy('name')
            ->get();
    }
}

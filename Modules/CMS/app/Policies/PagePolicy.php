<?php

declare(strict_types=1);

namespace Modules\CMS\Policies;

use Modules\CMS\Models\Page;
use Modules\Users\Models\User;

/**
 * Gestão do CMS é um concern do dono da clínica: `super_admin` (plataforma) e quem
 * tem a permissão `manage-cms` (seedada para `admin_clinica`, Fase 8). Registrada via
 * `Gate::policy(Page::class, ...)` — aqui o recurso É o Model Page (diferente de
 * MedicalRecord/Financial, cuja decisão era sobre um par (User, Patient)).
 *
 * A checagem de tenant do próprio Page (update/delete) vem de `CurrentTenant::ownsOrFail`
 * no Controller — esta Policy cobre papel/permissão; a Policy não recebe o tenant
 * resolvido da requisição, então não duplica o isolamento aqui.
 */
class PagePolicy
{
    private function manages(User $user): bool
    {
        return $user->hasRole('super_admin') || $user->hasPermissionTo('manage-cms');
    }

    public function viewAny(User $user): bool
    {
        return $this->manages($user);
    }

    public function view(User $user, Page $page): bool
    {
        return $this->manages($user);
    }

    public function create(User $user): bool
    {
        return $this->manages($user);
    }

    public function update(User $user, Page $page): bool
    {
        return $this->manages($user);
    }

    public function delete(User $user, Page $page): bool
    {
        return $this->manages($user);
    }
}

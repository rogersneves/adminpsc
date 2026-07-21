<?php

declare(strict_types=1);

namespace Modules\Scheduling\Policies;

use Modules\Users\Models\User;

class WaitingListEntryPolicy
{
    public function before(User $actor, string $ability): ?bool
    {
        return $actor->hasRole('super_admin') ? true : null;
    }

    public function create(User $actor): bool
    {
        return $actor->hasRole('paciente');
    }

    public function viewAny(User $actor): bool
    {
        return $actor->hasRole('psicologo') || ($actor->tenant_id !== null && $actor->hasPermissionTo('manage-users'));
    }
}

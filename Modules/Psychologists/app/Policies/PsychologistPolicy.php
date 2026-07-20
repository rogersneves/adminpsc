<?php

declare(strict_types=1);

namespace Modules\Psychologists\Policies;

use Modules\Users\Models\User;

class PsychologistPolicy
{
    public function before(User $actor, string $ability): ?bool
    {
        return $actor->hasRole('super_admin') ? true : null;
    }

    public function viewAny(User $actor): bool
    {
        return $actor->tenant_id !== null && $actor->hasPermissionTo('manage-users');
    }

    public function create(User $actor): bool
    {
        return $actor->tenant_id !== null && $actor->hasPermissionTo('manage-users');
    }
}

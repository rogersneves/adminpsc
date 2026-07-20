<?php

declare(strict_types=1);

namespace Modules\Authorization\Policies;

use Modules\Users\Models\User;

class UserPolicy
{
    /**
     * Super Admin passa por tudo; os demais casos seguem para os métodos abaixo.
     */
    public function before(User $actor, string $ability): ?bool
    {
        return $actor->hasRole('super_admin') ? true : null;
    }

    public function viewAny(User $actor): bool
    {
        return $actor->hasPermissionTo('manage-users');
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->is($target) || (
            $actor->tenant_id !== null
            && $actor->tenant_id === $target->tenant_id
            && $actor->hasPermissionTo('manage-users')
        );
    }

    public function update(User $actor, User $target): bool
    {
        return $this->view($actor, $target);
    }

    public function delete(User $actor, User $target): bool
    {
        return ! $actor->is($target)
            && $actor->tenant_id !== null
            && $actor->tenant_id === $target->tenant_id
            && $actor->hasPermissionTo('manage-users');
    }
}

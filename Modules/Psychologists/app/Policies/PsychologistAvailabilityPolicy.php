<?php

declare(strict_types=1);

namespace Modules\Psychologists\Policies;

use Modules\Psychologists\Models\Psychologist;
use Modules\Psychologists\Models\PsychologistAvailability;
use Modules\Users\Models\User;

class PsychologistAvailabilityPolicy
{
    public function before(User $actor, string $ability): ?bool
    {
        return $actor->hasRole('super_admin') ? true : null;
    }

    public function viewAny(User $actor, Psychologist $psychologist): bool
    {
        return $this->manages($actor, $psychologist);
    }

    public function create(User $actor, Psychologist $psychologist): bool
    {
        return $this->manages($actor, $psychologist);
    }

    public function delete(User $actor, PsychologistAvailability $availability): bool
    {
        return $this->manages($actor, $availability->psychologist);
    }

    private function manages(User $actor, Psychologist $psychologist): bool
    {
        if ($actor->id === $psychologist->user_id) {
            return true;
        }

        return $actor->tenant_id !== null
            && $actor->tenant_id === $psychologist->tenant_id
            && $actor->hasPermissionTo('manage-users');
    }
}

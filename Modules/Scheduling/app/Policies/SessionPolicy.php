<?php

declare(strict_types=1);

namespace Modules\Scheduling\Policies;

use Modules\Scheduling\Models\Session;
use Modules\Users\Models\User;

class SessionPolicy
{
    public function before(User $actor, string $ability): ?bool
    {
        return $actor->hasRole('super_admin') ? true : null;
    }

    public function create(User $actor): bool
    {
        return $actor->hasRole('paciente');
    }

    public function view(User $actor, Session $session): bool
    {
        return $this->owns($actor, $session) || $this->isClinicStaff($actor, $session);
    }

    public function cancel(User $actor, Session $session): bool
    {
        return $this->owns($actor, $session) || $this->isClinicStaff($actor, $session);
    }

    public function reschedule(User $actor, Session $session): bool
    {
        return $this->owns($actor, $session) || $this->isClinicStaff($actor, $session);
    }

    public function markStatus(User $actor, Session $session): bool
    {
        return $actor->id === $session->psychologist->user_id || $this->isClinicStaff($actor, $session);
    }

    private function owns(User $actor, Session $session): bool
    {
        return $actor->id === $session->patient->user_id || $actor->id === $session->psychologist->user_id;
    }

    private function isClinicStaff(User $actor, Session $session): bool
    {
        return $actor->tenant_id !== null
            && $actor->tenant_id === $session->tenant_id
            && $actor->hasPermissionTo('manage-users');
    }
}

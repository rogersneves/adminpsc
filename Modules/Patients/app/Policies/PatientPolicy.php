<?php

declare(strict_types=1);

namespace Modules\Patients\Policies;

use Modules\Patients\Models\Patient;
use Modules\Users\Models\User;

class PatientPolicy
{
    public function before(User $actor, string $ability): ?bool
    {
        return $actor->hasRole('super_admin') ? true : null;
    }

    public function view(User $actor, Patient $patient): bool
    {
        return $this->isSelf($actor, $patient) || $this->isClinicStaffForSameTenant($actor, $patient);
    }

    public function update(User $actor, Patient $patient): bool
    {
        return $this->isSelf($actor, $patient) || $this->isClinicStaffForSameTenant($actor, $patient);
    }

    private function isSelf(User $actor, Patient $patient): bool
    {
        return $patient->user_id !== null && $actor->id === $patient->user_id;
    }

    private function isClinicStaffForSameTenant(User $actor, Patient $patient): bool
    {
        return $actor->tenant_id !== null
            && $actor->tenant_id === $patient->tenant_id
            && $actor->hasPermissionTo('manage-users');
    }
}

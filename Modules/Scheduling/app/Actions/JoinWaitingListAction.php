<?php

declare(strict_types=1);

namespace Modules\Scheduling\Actions;

use Modules\Patients\Models\Patient;
use Modules\Psychologists\Models\Psychologist;
use Modules\Scheduling\Models\WaitingListEntry;

/**
 * Só grava a intenção do paciente — sem correspondência/notificação automática quando
 * um horário abre (depende do módulo Notifications, Fase 7). Ver docs/06-Roadmap.md.
 */
class JoinWaitingListAction
{
    public function __invoke(Patient $patient, Psychologist $psychologist, array $desiredPeriod): WaitingListEntry
    {
        return WaitingListEntry::query()->create([
            'tenant_id' => $patient->tenant_id,
            'patient_id' => $patient->id,
            'psychologist_id' => $psychologist->id,
            'desired_period' => $desiredPeriod,
            'status' => 'aberto',
        ]);
    }
}

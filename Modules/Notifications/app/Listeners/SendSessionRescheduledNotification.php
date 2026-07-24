<?php

declare(strict_types=1);

namespace Modules\Notifications\Listeners;

use Modules\Notifications\Notifications\SessionRescheduledNotification;
use Modules\Scheduling\Events\SessionWasRescheduled;

class SendSessionRescheduledNotification
{
    public function handle(SessionWasRescheduled $event): void
    {
        $newSession = $event->newSession->loadMissing(['patient.user', 'psychologist.user']);
        $oldSession = $event->oldSession;

        $patientUser = $newSession->patient->user;
        $psychologistUser = $newSession->psychologist->user;
        $psychologistName = $psychologistUser?->name ?? 'seu psicólogo';
        $patientName = $newSession->patient->display_name;

        $patientUser?->notify(new SessionRescheduledNotification($oldSession, $newSession, $psychologistName));
        $psychologistUser?->notify(new SessionRescheduledNotification($oldSession, $newSession, $patientName));
    }
}

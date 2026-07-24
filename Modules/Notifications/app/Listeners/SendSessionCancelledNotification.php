<?php

declare(strict_types=1);

namespace Modules\Notifications\Listeners;

use Modules\Notifications\Notifications\SessionCancelledNotification;
use Modules\Scheduling\Events\SessionWasCancelled;

class SendSessionCancelledNotification
{
    public function handle(SessionWasCancelled $event): void
    {
        $session = $event->session->loadMissing(['patient.user', 'psychologist.user']);

        $patientUser = $session->patient->user;
        $psychologistUser = $session->psychologist->user;
        $psychologistName = $psychologistUser?->name ?? 'seu psicólogo';
        $patientName = $session->patient->display_name;

        $patientUser?->notify(new SessionCancelledNotification($session, $psychologistName));
        $psychologistUser?->notify(new SessionCancelledNotification($session, $patientName));
    }
}

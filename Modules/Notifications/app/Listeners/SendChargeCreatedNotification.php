<?php

declare(strict_types=1);

namespace Modules\Notifications\Listeners;

use Modules\Financial\Events\ChargeWasCreated;
use Modules\Notifications\Notifications\ChargeCreatedNotification;

class SendChargeCreatedNotification
{
    public function handle(ChargeWasCreated $event): void
    {
        $charge = $event->charge->loadMissing('patient.user');

        $charge->patient->user?->notify(new ChargeCreatedNotification($charge));
    }
}

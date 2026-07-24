<?php

declare(strict_types=1);

namespace Modules\Notifications\Listeners;

use Modules\Notifications\Notifications\PaymentConfirmedNotification;
use Modules\Payments\Events\PaymentWasRecorded;

class SendPaymentConfirmedNotification
{
    public function handle(PaymentWasRecorded $event): void
    {
        $payment = $event->payment->loadMissing('charge.patient.user');

        $payment->charge->patient->user?->notify(new PaymentConfirmedNotification($payment));
    }
}

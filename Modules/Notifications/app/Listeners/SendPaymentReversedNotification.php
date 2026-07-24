<?php

declare(strict_types=1);

namespace Modules\Notifications\Listeners;

use Modules\Notifications\Notifications\PaymentReversedNotification;
use Modules\Payments\Events\PaymentWasReversed;

class SendPaymentReversedNotification
{
    public function handle(PaymentWasReversed $event): void
    {
        $payment = $event->payment->loadMissing('charge.patient.user');

        $payment->charge->patient->user?->notify(new PaymentReversedNotification($payment));
    }
}

<?php

declare(strict_types=1);

namespace Modules\Payments\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Payments\Models\Payment;

class PaymentWasReversed
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Payment $payment) {}
}

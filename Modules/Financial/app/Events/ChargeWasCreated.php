<?php

declare(strict_types=1);

namespace Modules\Financial\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Financial\Models\FinancialCharge;

class ChargeWasCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly FinancialCharge $charge) {}
}

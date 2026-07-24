<?php

declare(strict_types=1);

namespace Modules\Scheduling\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Scheduling\Models\Session;

class SessionWasCancelled
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Session $session) {}
}

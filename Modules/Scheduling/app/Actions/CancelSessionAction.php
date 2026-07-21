<?php

declare(strict_types=1);

namespace Modules\Scheduling\Actions;

use Modules\Scheduling\Enums\SessionStatus;
use Modules\Scheduling\Models\Session;
use Modules\Scheduling\Traits\EnsuresMinimumNotice;

class CancelSessionAction
{
    use EnsuresMinimumNotice;

    public function __invoke(Session $session): Session
    {
        $this->assertSufficientNotice($session);

        $session->update(['status' => SessionStatus::Cancelada]);

        return $session;
    }
}

<?php

declare(strict_types=1);

namespace Modules\Scheduling\Actions;

use InvalidArgumentException;
use Modules\Scheduling\Enums\SessionStatus;
use Modules\Scheduling\Models\Session;

/**
 * O psicólogo marca o desfecho de uma sessão depois que ela já aconteceu — não faz
 * sentido marcar "realizada"/"não compareceu" para uma sessão futura.
 */
class MarkSessionStatusAction
{
    public function __invoke(Session $session, SessionStatus $status): Session
    {
        if (! in_array($status, [SessionStatus::Realizada, SessionStatus::NaoCompareceu], true)) {
            throw new InvalidArgumentException('Status inválido para esta ação.');
        }

        if ($session->scheduled_at->isFuture()) {
            throw new InvalidArgumentException('Não é possível marcar o desfecho de uma sessão que ainda não aconteceu.');
        }

        $session->update(['status' => $status]);

        return $session;
    }
}

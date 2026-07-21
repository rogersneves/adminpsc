<?php

declare(strict_types=1);

namespace Modules\Scheduling\Traits;

use Modules\Scheduling\Exceptions\InsufficientNoticeException;
use Modules\Scheduling\Models\Session;

/**
 * Compartilhado por Cancel/RescheduleSessionAction — mesma regra, mesma mensagem.
 *
 * Usa `absolute: true` explicitamente: `diffInHours` retorna valor com sinal nesta
 * versão do Carbon (gotcha documentado no CLAUDE.md, descoberto na Fase 1 no timeout
 * de sessão — o mesmo cuidado se aplica aqui).
 */
trait EnsuresMinimumNotice
{
    private function assertSufficientNotice(Session $session): void
    {
        if ($session->scheduled_at->isPast()) {
            throw new InsufficientNoticeException('Não é possível cancelar ou reagendar uma sessão que já passou.');
        }

        $hoursNotice = now()->diffInHours($session->scheduled_at, absolute: true);

        if ($hoursNotice < (int) config('scheduling.minimum_reschedule_notice_hours')) {
            throw new InsufficientNoticeException(
                'Cancelamentos e reagendamentos precisam ser feitos com pelo menos '
                .config('scheduling.minimum_reschedule_notice_hours').'h de antecedência.'
            );
        }
    }
}

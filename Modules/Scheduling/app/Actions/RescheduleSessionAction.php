<?php

declare(strict_types=1);

namespace Modules\Scheduling\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Scheduling\Enums\SessionStatus;
use Modules\Scheduling\Models\Session;
use Modules\Scheduling\Traits\EnsuresMinimumNotice;

/**
 * Reagendar nunca "move" a sessão original in-place — cria uma nova sessão e marca a
 * antiga como `reagendada`, ligadas por `rescheduled_from_id` (histórico preservado,
 * docs/06-Roadmap.md Fase 3, decisão de escopo #4).
 */
class RescheduleSessionAction
{
    use EnsuresMinimumNotice;

    public function __construct(private readonly BookSessionAction $bookSession) {}

    public function __invoke(Session $session, CarbonImmutable $newStartsAt, int $durationMinutes): Session
    {
        $this->assertSufficientNotice($session);

        return DB::transaction(function () use ($session, $newStartsAt, $durationMinutes) {
            $newSession = ($this->bookSession)(
                $session->patient,
                $session->psychologist,
                $newStartsAt,
                $durationMinutes,
                $session->modality,
            );

            $newSession->update(['rescheduled_from_id' => $session->id]);
            $session->update(['status' => SessionStatus::Reagendada]);

            return $newSession;
        });
    }
}

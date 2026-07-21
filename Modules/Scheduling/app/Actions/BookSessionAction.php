<?php

declare(strict_types=1);

namespace Modules\Scheduling\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Patients\Models\Patient;
use Modules\Psychologists\Models\Psychologist;
use Modules\Scheduling\Enums\SessionModality;
use Modules\Scheduling\Enums\SessionStatus;
use Modules\Scheduling\Exceptions\SlotNoLongerAvailableException;
use Modules\Scheduling\Models\Session;
use Modules\Scheduling\Services\AvailabilityCalculator;

/**
 * Bloqueio transacional contra dupla reserva: trava a linha do Psychologist
 * (lockForUpdate), não linhas de Session — um horário que ainda não foi reservado não
 * tem linha pra travar em InnoDB. Isso serializa toda tentativa de reserva *daquele*
 * psicólogo, o suficiente pra evitar duas pessoas reservando o mesmo horário ao mesmo
 * tempo (docs/06-Roadmap.md Fase 3, decisão de escopo #3).
 */
class BookSessionAction
{
    public function __construct(private readonly AvailabilityCalculator $calculator) {}

    public function __invoke(
        Patient $patient,
        Psychologist $psychologist,
        CarbonImmutable $startsAt,
        int $durationMinutes,
        SessionModality $modality,
    ): Session {
        return DB::transaction(function () use ($patient, $psychologist, $startsAt, $durationMinutes, $modality) {
            $lockedPsychologist = Psychologist::query()
                ->whereKey($psychologist->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $this->calculator->isSlotAvailable($lockedPsychologist, $startsAt, $durationMinutes)) {
                throw new SlotNoLongerAvailableException('Esse horário não está mais disponível.');
            }

            return Session::query()->create([
                'tenant_id' => $patient->tenant_id,
                'patient_id' => $patient->id,
                'psychologist_id' => $lockedPsychologist->id,
                'scheduled_at' => $startsAt,
                'duration_minutes' => $durationMinutes,
                'modality' => $modality,
                'status' => SessionStatus::Agendada,
            ]);
        });
    }
}

<?php

declare(strict_types=1);

namespace Modules\Reports\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Modules\Scheduling\Models\Session;

class BuildSessionsReportAction
{
    /**
     * @param  list<string>|null  $patientIds  null = sem filtro de book (admin vendo tudo)
     */
    public function __invoke(
        ?array $patientIds,
        ?string $patientId = null,
        ?CarbonImmutable $from = null,
        ?CarbonImmutable $to = null,
    ): Collection {
        $query = Session::query()->with(['patient', 'psychologist.user']);

        if ($patientIds !== null) {
            $query->whereIn('patient_id', $patientIds);
        }

        if ($patientId !== null) {
            $query->where('patient_id', $patientId);
        }

        if ($from !== null) {
            $query->where('scheduled_at', '>=', $from);
        }

        if ($to !== null) {
            $query->where('scheduled_at', '<=', $to);
        }

        return $query->orderBy('scheduled_at')
            ->get()
            ->map(fn (Session $session) => [
                'patient_name' => $session->patient->display_name,
                'psychologist_name' => $session->psychologist->user->name,
                'scheduled_at' => $session->scheduled_at->toIso8601String(),
                'duration_minutes' => $session->duration_minutes,
                'modality' => $session->modality->value,
                'status' => $session->status->value,
            ]);
    }
}

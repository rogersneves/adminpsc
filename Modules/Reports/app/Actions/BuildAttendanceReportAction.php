<?php

declare(strict_types=1);

namespace Modules\Reports\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Modules\Patients\Models\Patient;
use Modules\Scheduling\Enums\SessionStatus;
use Modules\Scheduling\Models\Session;

/**
 * Comparecimento = Realizada / (Realizada + NaoCompareceu) por paciente. Cancelada e
 * Reagendada ficam fora do denominador — são mudança de agenda, não falha de
 * comparecimento (docs/06-Roadmap.md Fase 6, decisão de escopo #8).
 */
class BuildAttendanceReportAction
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
        $query = Session::query()->whereIn('status', [
            SessionStatus::Realizada->value,
            SessionStatus::NaoCompareceu->value,
        ]);

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

        $sessions = $query->get()->groupBy('patient_id');

        if ($sessions->isEmpty()) {
            return collect();
        }

        $patients = Patient::query()->whereIn('id', $sessions->keys())->get()->keyBy('id');

        return $sessions->map(function (Collection $patientSessions, string $patientId) use ($patients) {
            $realizada = $patientSessions->where('status', SessionStatus::Realizada)->count();
            $naoCompareceu = $patientSessions->where('status', SessionStatus::NaoCompareceu)->count();
            $total = $realizada + $naoCompareceu;

            return [
                'patient_name' => $patients->get($patientId)?->display_name ?? '—',
                'realizada' => $realizada,
                'nao_compareceu' => $naoCompareceu,
                'attendance_rate' => $total > 0 ? round($realizada / $total, 4) : 0.0,
            ];
        })->values();
    }
}

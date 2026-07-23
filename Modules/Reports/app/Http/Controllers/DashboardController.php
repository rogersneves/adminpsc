<?php

declare(strict_types=1);

namespace Modules\Reports\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Financial\Enums\ChargeStatus;
use Modules\Financial\Models\FinancialCharge;
use Modules\Patients\Models\Patient;
use Modules\Payments\Models\Payment;
use Modules\Psychologists\Models\Psychologist;
use Modules\Scheduling\Enums\SessionStatus;
use Modules\Scheduling\Models\Session;
use Modules\Tenant\Support\CurrentTenant;

/**
 * Só `psicologo` e `paciente` têm dashboard com dados reais nesta fase (únicos papéis
 * citados no bullet do roadmap) — qualquer outro papel continua vendo o card genérico
 * (docs/06-Roadmap.md Fase 6, decisão de escopo #9).
 */
class DashboardController extends Controller
{
    public function index(Request $request, CurrentTenant $currentTenant): Response
    {
        $user = $request->user();
        $tenant = $currentTenant->get();

        if ($user->hasRole('psicologo')) {
            $psychologist = Psychologist::query()->where('user_id', $user->id)->first();

            if ($psychologist !== null) {
                return Inertia::render('Dashboard', [
                    'tenant' => $tenant ? ['id' => $tenant->id, 'name' => $tenant->name, 'slug' => $tenant->slug] : null,
                    'role' => 'psicologo',
                    'psychologistDashboard' => $this->psychologistData($psychologist),
                ]);
            }
        }

        if ($user->hasRole('paciente')) {
            $patient = Patient::query()->where('user_id', $user->id)->first();

            if ($patient !== null) {
                return Inertia::render('Dashboard', [
                    'tenant' => $tenant ? ['id' => $tenant->id, 'name' => $tenant->name, 'slug' => $tenant->slug] : null,
                    'role' => 'paciente',
                    'patientDashboard' => $this->patientData($patient),
                ]);
            }
        }

        return Inertia::render('Dashboard', [
            'tenant' => $tenant ? ['id' => $tenant->id, 'name' => $tenant->name, 'slug' => $tenant->slug] : null,
            'role' => null,
        ]);
    }

    private function psychologistData(Psychologist $psychologist): array
    {
        $patientIds = Session::query()
            ->where('psychologist_id', $psychologist->id)
            ->distinct()
            ->pluck('patient_id');

        $today = today();
        $weekStart = $today->copy()->startOfWeek();
        $weekEnd = $today->copy()->endOfWeek();

        $agendaToday = Session::query()
            ->where('psychologist_id', $psychologist->id)
            ->whereBetween('scheduled_at', [$today->copy()->startOfDay(), $today->copy()->endOfDay()])
            ->orderBy('scheduled_at')
            ->with('patient')
            ->get()
            ->map(fn (Session $s) => [
                'id' => $s->id,
                'patient_name' => $s->patient->display_name,
                'scheduled_at' => $s->scheduled_at->toIso8601String(),
                'status' => $s->status->value,
            ]);

        $sessionsThisWeekCount = Session::query()
            ->where('psychologist_id', $psychologist->id)
            ->whereBetween('scheduled_at', [$weekStart, $weekEnd])
            ->count();

        $revenueThisMonth = (float) Payment::query()
            ->whereNull('reversed_at')
            ->whereIn('charge_id', FinancialCharge::query()->whereIn('patient_id', $patientIds)->pluck('id'))
            ->whereBetween('paid_at', [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()])
            ->sum('amount');

        $pendingCharges = FinancialCharge::query()
            ->whereIn('patient_id', $patientIds)
            ->whereIn('status', [ChargeStatus::Vencido->value, ChargeStatus::Parcial->value])
            ->with('patient')
            ->get();

        $pendingTotal = $pendingCharges->sum(fn (FinancialCharge $c) => $c->totalDue() - $c->totalPaid());

        $inactiveDays = (int) config('reports.inactive_patient_days');
        $activeWindowStart = $today->copy()->subDays($inactiveDays)->startOfDay();

        $activePatientIds = Session::query()
            ->whereIn('patient_id', $patientIds)
            ->where('scheduled_at', '>=', $activeWindowStart)
            ->distinct()
            ->pluck('patient_id');

        $patients = Patient::query()->whereIn('id', $patientIds)->get();

        $birthdayPatients = $patients->filter(function (Patient $patient) use ($today) {
            $birthDate = $patient->birth_date_encrypted;

            if (! $birthDate) {
                return false;
            }

            return (int) Carbon::parse($birthDate)->format('n') === (int) $today->format('n');
        })->map(fn (Patient $p) => ['id' => $p->id, 'display_name' => $p->display_name])->values();

        return [
            'agenda_today' => $agendaToday,
            'sessions_this_week' => $sessionsThisWeekCount,
            'revenue_this_month' => $revenueThisMonth,
            'pending_charges_total' => round($pendingTotal, 2),
            'pending_charges_count' => $pendingCharges->count(),
            'active_patients_count' => $activePatientIds->count(),
            'inactive_patients_count' => $patients->count() - $activePatientIds->count(),
            'total_patients_count' => $patients->count(),
            'birthday_patients' => $birthdayPatients,
        ];
    }

    private function patientData(Patient $patient): array
    {
        $today = now();

        $nextSession = Session::query()
            ->where('patient_id', $patient->id)
            ->whereIn('status', [SessionStatus::Agendada->value, SessionStatus::Confirmada->value])
            ->where('scheduled_at', '>=', $today)
            ->orderBy('scheduled_at')
            ->first();

        $pendingCharges = FinancialCharge::query()
            ->where('patient_id', $patient->id)
            ->whereIn('status', [ChargeStatus::EmAberto->value, ChargeStatus::Vencido->value, ChargeStatus::Parcial->value])
            ->get();

        $history = Session::query()
            ->where('patient_id', $patient->id)
            ->where('scheduled_at', '<', $today)
            ->orderByDesc('scheduled_at')
            ->limit(5)
            ->get()
            ->map(fn (Session $s) => [
                'id' => $s->id,
                'scheduled_at' => $s->scheduled_at->toIso8601String(),
                'status' => $s->status->value,
            ]);

        return [
            'next_session' => $nextSession ? [
                'id' => $nextSession->id,
                'scheduled_at' => $nextSession->scheduled_at->toIso8601String(),
                'status' => $nextSession->status->value,
            ] : null,
            'pending_charges_total' => round($pendingCharges->sum(fn (FinancialCharge $c) => $c->totalDue() - $c->totalPaid()), 2),
            'pending_charges_count' => $pendingCharges->count(),
            'history' => $history,
            'patient_id' => $patient->id,
        ];
    }
}

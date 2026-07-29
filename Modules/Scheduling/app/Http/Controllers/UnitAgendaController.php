<?php

declare(strict_types=1);

namespace Modules\Scheduling\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Scheduling\Models\Session;
use Modules\Settings\Services\UnitScope;

/**
 * Agenda por unidade (marco: secretárias com escopo próprio). Restrito a
 * `manage-scheduling`. Uma secretária vê apenas as sessões das unidades a que está
 * lotada (UnitScope); admin da clínica vê todas. Leitura por ora — gestão de sessões
 * continua nos fluxos já existentes do paciente/psicólogo.
 */
class UnitAgendaController extends Controller
{
    public function index(Request $request, UnitScope $scope): Response
    {
        $user = $request->user();
        $unitIds = $scope->unitIdsFor($user); // null = todas as unidades do tenant

        $sessions = Session::query()
            ->with(['patient:id,display_name', 'psychologist.user:id,name', 'unit:id,name'])
            ->when($unitIds !== null, fn ($q) => $q->whereIn('unit_id', $unitIds))
            ->where('scheduled_at', '>=', now()->startOfDay())
            ->orderBy('scheduled_at')
            ->limit(200)
            ->get()
            ->map(fn (Session $session) => [
                'id' => $session->id,
                'scheduled_at' => $session->scheduled_at?->toIso8601String(),
                'status' => $session->status->value,
                'modality' => $session->modality->value,
                'meeting_url' => $session->meeting_url,
                'patient_name' => $session->patient?->display_name,
                'psychologist_name' => $session->psychologist?->user?->name,
                'unit_name' => $session->unit?->name,
            ]);

        return Inertia::render('Scheduling/UnitAgenda', [
            'sessions' => $sessions,
            'scoped' => $unitIds !== null,
            'units' => $scope->tenantUnits($user)->map(fn ($u) => ['id' => $u->id, 'name' => $u->name]),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Financial\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Patients\Models\Patient;

/**
 * Lista mínima do tenant (nome + link "ver financeiro"), só o necessário pra navegar
 * até o financeiro de um paciente — não é a tela completa de gestão de pacientes
 * ainda pendente da Fase 2 (busca, edição, desativação).
 */
class FinancialPatientsController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless(
            $request->user()->hasRole('super_admin') || $request->user()->hasPermissionTo('manage-financial'),
            403,
        );

        $patients = Patient::query()
            ->orderBy('display_name')
            ->get()
            ->map(fn (Patient $patient) => [
                'id' => $patient->id,
                'display_name' => $patient->display_name,
            ]);

        return Inertia::render('Financial/PatientsList', ['patients' => $patients]);
    }
}

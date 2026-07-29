<?php

declare(strict_types=1);

namespace Modules\Patients\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Financial\Models\HealthPlan;
use Modules\Patients\Actions\UpdatePatientProfileAction;
use Modules\Patients\Http\Requests\UpdatePatientProfileRequest;
use Modules\Patients\Models\Patient;

class PatientProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $patient = Patient::query()->where('user_id', $request->user()->id)->firstOrFail();

        $this->authorize('view', $patient);

        return Inertia::render('Patients/Profile', [
            'patient' => [
                'display_name' => $patient->display_name,
                'has_document_number' => $patient->document_number_encrypted !== null,
                'birth_date' => $patient->birth_date_encrypted,
                'phones' => $patient->phones_encrypted ?? [],
                'emergency_contacts' => $patient->emergency_contacts_encrypted ?? [],
                'address' => $patient->address_encrypted,
                'health_plan_id' => $patient->health_plan_id,
                'guardians' => $patient->guardians->map(fn ($guardian) => [
                    'name' => $guardian->name,
                    'relationship' => $guardian->relationship,
                ]),
            ],
            // Convênios ativos da clínica (marco: convênios).
            'healthPlans' => HealthPlan::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name]),
        ]);
    }

    public function update(
        UpdatePatientProfileRequest $request,
        UpdatePatientProfileAction $action,
    ): RedirectResponse {
        $patient = Patient::query()->where('user_id', $request->user()->id)->firstOrFail();

        $this->authorize('update', $patient);

        $action($patient, $request->validated());

        return back()->with('status', 'Perfil atualizado.');
    }
}

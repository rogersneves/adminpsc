<?php

declare(strict_types=1);

namespace Modules\MedicalRecords\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\MedicalRecords\Actions\CreateMedicalRecordEntryAction;
use Modules\MedicalRecords\Http\Requests\StoreMedicalRecordEntryRequest;
use Modules\MedicalRecords\Models\MedicalRecordEntry;
use Modules\Patients\Models\Patient;
use Modules\Psychologists\Models\Psychologist;
use Modules\Scheduling\Enums\SessionStatus;
use Modules\Scheduling\Models\Session;
use Modules\Tenant\Support\CurrentTenant;

class MedicalRecordController extends Controller
{
    public function myPatients(Request $request): Response
    {
        $psychologist = Psychologist::query()->where('user_id', $request->user()->id)->firstOrFail();

        $patients = Patient::query()
            ->whereIn('id', Session::query()
                ->where('psychologist_id', $psychologist->id)
                ->select('patient_id')
                ->distinct())
            ->get()
            ->map(fn (Patient $patient) => [
                'id' => $patient->id,
                'display_name' => $patient->display_name,
            ]);

        return Inertia::render('MedicalRecords/MyPatients', ['patients' => $patients]);
    }

    public function show(Request $request, Patient $patient, CurrentTenant $currentTenant): Response
    {
        $currentTenant->ownsOrFail($patient);
        $this->authorize('medicalRecords.view', $patient);

        $psychologist = Psychologist::query()->where('user_id', $request->user()->id)->first();

        $entries = MedicalRecordEntry::query()
            ->where('patient_id', $patient->id)
            ->with(['psychologist.user:id,name', 'attachments'])
            ->orderByDesc('version')
            ->get()
            ->map(fn (MedicalRecordEntry $entry) => [
                'id' => $entry->id,
                'version' => $entry->version,
                'author' => $entry->psychologist->user->name,
                'created_at' => $entry->created_at->toIso8601String(),
                'notes' => $entry->content_encrypted['notes'] ?? null,
                'therapeutic_objectives' => $entry->content_encrypted['therapeutic_objectives'] ?? null,
                'therapeutic_plan' => $entry->content_encrypted['therapeutic_plan'] ?? null,
                'attachments' => $entry->attachments->map(fn ($a) => [
                    'id' => $a->id,
                    'name' => $a->original_filename_encrypted,
                ]),
            ]);

        $availableSessions = $psychologist
            ? Session::query()
                ->where('patient_id', $patient->id)
                ->where('psychologist_id', $psychologist->id)
                ->where('status', SessionStatus::Realizada)
                ->orderByDesc('scheduled_at')
                ->get()
                ->map(fn (Session $session) => [
                    'id' => $session->id,
                    'scheduled_at' => $session->scheduled_at->toIso8601String(),
                ])
            : [];

        return Inertia::render('MedicalRecords/Record', [
            'patient' => ['id' => $patient->id, 'display_name' => $patient->display_name],
            'entries' => $entries,
            'availableSessions' => $availableSessions,
        ]);
    }

    public function store(
        StoreMedicalRecordEntryRequest $request,
        Patient $patient,
        CreateMedicalRecordEntryAction $action,
        CurrentTenant $currentTenant,
    ): RedirectResponse {
        $currentTenant->ownsOrFail($patient);
        $this->authorize('medicalRecords.create', $patient);

        $psychologist = Psychologist::query()->where('user_id', $request->user()->id)->firstOrFail();

        $action(
            $patient,
            $psychologist,
            $request->contentData(),
            $request->input('session_id'),
            $request->file('attachment'),
        );

        return back()->with('status', 'Novo registro adicionado ao prontuário.');
    }
}

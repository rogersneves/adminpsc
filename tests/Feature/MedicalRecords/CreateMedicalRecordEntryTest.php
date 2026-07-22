<?php

declare(strict_types=1);

namespace Tests\Feature\MedicalRecords;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\MedicalRecords\Models\MedicalRecordEntry;
use Modules\Patients\Models\Patient;
use Modules\Psychologists\Models\Psychologist;
use Modules\Scheduling\Enums\SessionModality;
use Modules\Scheduling\Enums\SessionStatus;
use Modules\Scheduling\Models\Session;
use Modules\Tenant\Models\Tenant;
use Modules\Users\Models\User;
use Tests\TestCase;

class CreateMedicalRecordEntryTest extends TestCase
{
    use RefreshDatabase;

    private function makeTreatingPair(): array
    {
        $tenant = Tenant::factory()->create();

        $psychologistUser = User::factory()->create(['tenant_id' => $tenant->id]);
        $psychologistUser->assignRole('psicologo');
        $psychologist = Psychologist::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $psychologistUser->id,
            'professional_registry_encrypted' => 'CRP 00/00000',
            'specialties' => [],
            'default_session_duration_minutes' => 50,
        ]);

        $patientUser = User::factory()->create(['tenant_id' => $tenant->id]);
        $patientUser->assignRole('paciente');
        $patient = Patient::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $patientUser->id,
            'display_name' => $patientUser->name,
            'email' => $patientUser->email,
        ]);

        Session::query()->create([
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
            'psychologist_id' => $psychologist->id,
            'scheduled_at' => now()->subDay(),
            'duration_minutes' => 50,
            'modality' => SessionModality::Online,
            'status' => SessionStatus::Realizada,
        ]);

        return compact('tenant', 'psychologistUser', 'psychologist', 'patientUser', 'patient');
    }

    public function test_first_entry_is_version_one(): void
    {
        ['psychologistUser' => $psychologistUser, 'patient' => $patient] = $this->makeTreatingPair();

        $response = $this->actingAs($psychologistUser)->post("/pacientes/{$patient->id}/prontuario", [
            'notes' => 'Primeira sessão, paciente relatou ansiedade.',
            'therapeutic_objectives' => 'Reduzir crises de ansiedade.',
            'therapeutic_plan' => 'TCC semanal.',
        ]);

        $response->assertRedirect();

        $entry = MedicalRecordEntry::query()->where('patient_id', $patient->id)->first();
        $this->assertSame(1, $entry->version);
        $this->assertNull($entry->previous_version_id);
        $this->assertSame('Primeira sessão, paciente relatou ansiedade.', $entry->content_encrypted['notes']);
    }

    public function test_second_entry_increments_version_and_links_to_the_first(): void
    {
        ['psychologistUser' => $psychologistUser, 'patient' => $patient] = $this->makeTreatingPair();

        $this->actingAs($psychologistUser)->post("/pacientes/{$patient->id}/prontuario", [
            'notes' => 'Nota 1',
            'therapeutic_objectives' => 'Objetivo inicial',
            'therapeutic_plan' => 'Plano inicial',
        ]);
        $first = MedicalRecordEntry::query()->where('patient_id', $patient->id)->first();

        $this->actingAs($psychologistUser)->post("/pacientes/{$patient->id}/prontuario", [
            'notes' => 'Nota 2 — paciente relatou melhora',
        ]);

        $second = MedicalRecordEntry::query()->where('patient_id', $patient->id)->orderByDesc('version')->first();

        $this->assertSame(2, $second->version);
        $this->assertSame($first->id, $second->previous_version_id);
        $this->assertSame('Nota 2 — paciente relatou melhora', $second->content_encrypted['notes']);
        // campos não enviados herdam da versão anterior
        $this->assertSame('Objetivo inicial', $second->content_encrypted['therapeutic_objectives']);
        $this->assertSame('Plano inicial', $second->content_encrypted['therapeutic_plan']);
    }

    public function test_content_is_encrypted_at_rest(): void
    {
        ['psychologistUser' => $psychologistUser, 'patient' => $patient] = $this->makeTreatingPair();

        $this->actingAs($psychologistUser)->post("/pacientes/{$patient->id}/prontuario", [
            'notes' => 'Informação clínica sensível não pode vazar em texto puro',
        ]);

        $row = DB::table('medical_record_entries')->where('patient_id', $patient->id)->first();

        $this->assertStringNotContainsString('Informação clínica sensível', $row->content_encrypted);
    }
}

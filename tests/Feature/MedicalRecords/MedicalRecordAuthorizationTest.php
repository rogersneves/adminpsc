<?php

declare(strict_types=1);

namespace Tests\Feature\MedicalRecords;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Patients\Models\Patient;
use Modules\Psychologists\Models\Psychologist;
use Modules\Scheduling\Enums\SessionModality;
use Modules\Scheduling\Enums\SessionStatus;
use Modules\Scheduling\Models\Session;
use Modules\Tenant\Models\Tenant;
use Modules\Users\Models\User;
use Tests\TestCase;

class MedicalRecordAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function makePsychologist(Tenant $tenant): array
    {
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole('psicologo');
        $psychologist = Psychologist::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'professional_registry_encrypted' => 'CRP 00/00000',
            'specialties' => [],
            'default_session_duration_minutes' => 50,
        ]);

        return [$user, $psychologist];
    }

    private function makePatient(Tenant $tenant): array
    {
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole('paciente');
        $patient = Patient::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'display_name' => $user->name,
            'email' => $user->email,
        ]);

        return [$user, $patient];
    }

    public function test_psychologist_without_a_session_with_the_patient_cannot_view_the_record(): void
    {
        $tenant = Tenant::factory()->create();
        [$psychologistUser] = $this->makePsychologist($tenant);
        [, $patient] = $this->makePatient($tenant);

        $response = $this->actingAs($psychologistUser)->get("/pacientes/{$patient->id}/prontuario");

        $response->assertForbidden();
    }

    public function test_psychologist_who_treated_the_patient_can_view_and_create(): void
    {
        $tenant = Tenant::factory()->create();
        [$psychologistUser, $psychologist] = $this->makePsychologist($tenant);
        [, $patient] = $this->makePatient($tenant);

        Session::query()->create([
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
            'psychologist_id' => $psychologist->id,
            'scheduled_at' => now()->subDay(),
            'duration_minutes' => 50,
            'modality' => SessionModality::Online,
            'status' => SessionStatus::Realizada,
        ]);

        $response = $this->actingAs($psychologistUser)->get("/pacientes/{$patient->id}/prontuario");
        $response->assertOk();

        $response = $this->actingAs($psychologistUser)->post("/pacientes/{$patient->id}/prontuario", [
            'notes' => 'Ok',
        ]);
        $response->assertRedirect();
    }

    public function test_admin_clinica_of_the_same_tenant_can_view_but_patient_cannot(): void
    {
        $tenant = Tenant::factory()->create();
        [, $patient] = $this->makePatient($tenant);

        $admin = User::factory()->create(['tenant_id' => $tenant->id]);
        $admin->assignRole('admin_clinica');

        $this->actingAs($admin)->get("/pacientes/{$patient->id}/prontuario")->assertOk();

        $patientUser = User::query()->find($patient->user_id);
        $this->actingAs($patientUser)->get("/pacientes/{$patient->id}/prontuario")->assertForbidden();
    }

    public function test_patient_from_another_tenant_is_not_found(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        [$psychologistUser] = $this->makePsychologist($tenantA);
        [, $patientB] = $this->makePatient($tenantB);

        $response = $this->actingAs($psychologistUser)->get("/pacientes/{$patientB->id}/prontuario");

        $response->assertNotFound();
    }
}

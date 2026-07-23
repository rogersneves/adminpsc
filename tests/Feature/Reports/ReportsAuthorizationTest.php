<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Patients\Models\Patient;
use Modules\Psychologists\Models\Psychologist;
use Modules\Scheduling\Enums\SessionModality;
use Modules\Scheduling\Enums\SessionStatus;
use Modules\Scheduling\Models\Session;
use Modules\Tenant\Models\Tenant;
use Modules\Users\Models\User;
use Tests\TestCase;

class ReportsAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function makePsychologistWithPatient(Tenant $tenant): array
    {
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
            'scheduled_at' => now()->addDay(),
            'duration_minutes' => 50,
            'modality' => SessionModality::Online,
            'status' => SessionStatus::Agendada,
        ]);

        return [$psychologistUser, $patient];
    }

    public function test_psychologist_sees_only_own_book(): void
    {
        $tenant = Tenant::factory()->create();
        [$psychologistUser, $ownPatient] = $this->makePsychologistWithPatient($tenant);
        [, $otherPatient] = $this->makePsychologistWithPatient($tenant);

        $response = $this->actingAs($psychologistUser)->get('/relatorios/sessoes');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('rows', 1)
            ->where('rows.0.patient_name', $ownPatient->display_name));
    }

    public function test_admin_clinica_sees_everyone_in_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $this->makePsychologistWithPatient($tenant);
        $this->makePsychologistWithPatient($tenant);

        $admin = User::factory()->create(['tenant_id' => $tenant->id]);
        $admin->assignRole('admin_clinica');

        $response = $this->actingAs($admin)->get('/relatorios/sessoes');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('rows', 2));
    }

    public function test_secretaria_and_patient_are_forbidden(): void
    {
        $tenant = Tenant::factory()->create();
        [, $patient] = $this->makePsychologistWithPatient($tenant);

        $secretaria = User::factory()->create(['tenant_id' => $tenant->id]);
        $secretaria->assignRole('secretaria');
        $this->actingAs($secretaria)->get('/relatorios/sessoes')->assertForbidden();

        $patientUser = User::query()->find($patient->user_id);
        $this->actingAs($patientUser)->get('/relatorios/sessoes')->assertForbidden();
    }

    public function test_financial_and_attendance_reports_are_also_scoped(): void
    {
        $tenant = Tenant::factory()->create();
        [$psychologistUser] = $this->makePsychologistWithPatient($tenant);

        $this->actingAs($psychologistUser)->get('/relatorios/financeiro')->assertOk();
        $this->actingAs($psychologistUser)->get('/relatorios/comparecimento')->assertOk();
    }
}

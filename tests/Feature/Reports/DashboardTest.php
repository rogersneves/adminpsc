<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Financial\Enums\ChargeStatus;
use Modules\Financial\Models\FinancialCharge;
use Modules\Patients\Models\Patient;
use Modules\Psychologists\Models\Psychologist;
use Modules\Scheduling\Enums\SessionModality;
use Modules\Scheduling\Enums\SessionStatus;
use Modules\Scheduling\Models\Session;
use Modules\Tenant\Models\Tenant;
use Modules\Users\Models\User;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_psychologist_receives_psychologist_dashboard_data(): void
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
            'scheduled_at' => today()->setTime(10, 0),
            'duration_minutes' => 50,
            'modality' => SessionModality::Online,
            'status' => SessionStatus::Agendada,
        ]);

        FinancialCharge::query()->create([
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
            'amount' => 100,
            'due_date' => now()->subDays(3),
            'status' => ChargeStatus::Vencido,
        ]);

        $response = $this->actingAs($psychologistUser)->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('role', 'psicologo')
            ->has('psychologistDashboard.agenda_today', 1)
            ->where('psychologistDashboard.total_patients_count', 1)
            ->where('psychologistDashboard.pending_charges_count', 1));
    }

    public function test_patient_receives_patient_dashboard_data(): void
    {
        $tenant = Tenant::factory()->create();

        $patientUser = User::factory()->create(['tenant_id' => $tenant->id]);
        $patientUser->assignRole('paciente');
        $patient = Patient::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $patientUser->id,
            'display_name' => $patientUser->name,
            'email' => $patientUser->email,
        ]);

        FinancialCharge::query()->create([
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
            'amount' => 50,
            'due_date' => now()->addDays(5),
            'status' => ChargeStatus::EmAberto,
        ]);

        $response = $this->actingAs($patientUser)->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('role', 'paciente')
            ->where('patientDashboard.pending_charges_count', 1)
            ->where('patientDashboard.patient_id', $patient->id));
    }

    public function test_other_roles_get_no_role_specific_dashboard(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->create(['tenant_id' => $tenant->id]);
        $admin->assignRole('admin_clinica');

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('role', null));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Financial;

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

class FinancialAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function makePatient(Tenant $tenant): Patient
    {
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole('paciente');

        return Patient::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'display_name' => $user->name,
            'email' => $user->email,
        ]);
    }

    private function makeCharge(Patient $patient): FinancialCharge
    {
        return FinancialCharge::query()->create([
            'tenant_id' => $patient->tenant_id,
            'patient_id' => $patient->id,
            'amount' => 100,
            'due_date' => now()->addDays(5),
            'status' => ChargeStatus::EmAberto,
        ]);
    }

    public function test_financeiro_can_view_and_create_charges(): void
    {
        $tenant = Tenant::factory()->create();
        $patient = $this->makePatient($tenant);

        $financeiro = User::factory()->create(['tenant_id' => $tenant->id]);
        $financeiro->assignRole('financeiro');

        $this->actingAs($financeiro)->get("/pacientes/{$patient->id}/financeiro")->assertOk();

        $response = $this->actingAs($financeiro)->post("/pacientes/{$patient->id}/financeiro/cobrancas", [
            'amount' => '50.00',
            'due_date' => now()->addDays(10)->toDateString(),
        ]);
        $response->assertRedirect();
    }

    public function test_psychologist_who_treated_the_patient_can_view_but_not_manage(): void
    {
        $tenant = Tenant::factory()->create();
        $patient = $this->makePatient($tenant);

        $psychologistUser = User::factory()->create(['tenant_id' => $tenant->id]);
        $psychologistUser->assignRole('psicologo');
        $psychologist = Psychologist::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $psychologistUser->id,
            'professional_registry_encrypted' => 'CRP 00/00000',
            'specialties' => [],
            'default_session_duration_minutes' => 50,
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

        $this->actingAs($psychologistUser)->get("/pacientes/{$patient->id}/financeiro")->assertOk();

        $response = $this->actingAs($psychologistUser)->post("/pacientes/{$patient->id}/financeiro/cobrancas", [
            'amount' => '50.00',
            'due_date' => now()->addDays(10)->toDateString(),
        ]);
        $response->assertForbidden();
    }

    public function test_psychologist_without_a_session_cannot_view(): void
    {
        $tenant = Tenant::factory()->create();
        $patient = $this->makePatient($tenant);

        $psychologistUser = User::factory()->create(['tenant_id' => $tenant->id]);
        $psychologistUser->assignRole('psicologo');
        Psychologist::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $psychologistUser->id,
            'professional_registry_encrypted' => 'CRP 00/00000',
            'specialties' => [],
            'default_session_duration_minutes' => 50,
        ]);

        $this->actingAs($psychologistUser)->get("/pacientes/{$patient->id}/financeiro")->assertForbidden();
    }

    public function test_secretaria_cannot_view(): void
    {
        $tenant = Tenant::factory()->create();
        $patient = $this->makePatient($tenant);

        $secretaria = User::factory()->create(['tenant_id' => $tenant->id]);
        $secretaria->assignRole('secretaria');
        $this->actingAs($secretaria)->get("/pacientes/{$patient->id}/financeiro")->assertForbidden();
    }

    public function test_patient_can_view_own_financial_situation_but_not_someone_elses(): void
    {
        $tenant = Tenant::factory()->create();
        $patient = $this->makePatient($tenant);
        $otherPatient = $this->makePatient($tenant);

        $patientUser = User::query()->find($patient->user_id);

        $response = $this->actingAs($patientUser)->get("/pacientes/{$patient->id}/financeiro");
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('canManage', false));

        $this->actingAs($patientUser)->get("/pacientes/{$otherPatient->id}/financeiro")->assertForbidden();
    }

    public function test_patient_from_another_tenant_is_not_found(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $financeiro = User::factory()->create(['tenant_id' => $tenantA->id]);
        $financeiro->assignRole('financeiro');

        $patientB = $this->makePatient($tenantB);

        $this->actingAs($financeiro)->get("/pacientes/{$patientB->id}/financeiro")->assertNotFound();
    }

    public function test_charge_from_another_tenant_is_not_found_when_recording_a_payment(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $financeiro = User::factory()->create(['tenant_id' => $tenantA->id]);
        $financeiro->assignRole('financeiro');

        $patientB = $this->makePatient($tenantB);
        $chargeB = $this->makeCharge($patientB);

        $response = $this->actingAs($financeiro)->post("/financeiro/cobrancas/{$chargeB->id}/pagamentos", [
            'amount' => '10.00',
            'method' => 'dinheiro',
        ]);

        $response->assertNotFound();
    }
}

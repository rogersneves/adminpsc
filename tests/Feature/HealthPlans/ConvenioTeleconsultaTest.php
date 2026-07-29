<?php

declare(strict_types=1);

namespace Tests\Feature\HealthPlans;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Financial\Actions\CreateChargeAction;
use Modules\Financial\Models\HealthPlan;
use Modules\MedicalRecords\Contracts\SignatureProviderInterface;
use Modules\Patients\Models\Patient;
use Modules\Payments\Contracts\InvoiceIssuerInterface;
use Modules\Psychologists\Models\Psychologist;
use Modules\Scheduling\Enums\SessionModality;
use Modules\Scheduling\Enums\SessionStatus;
use Modules\Scheduling\Models\Session;
use Modules\Tenant\Models\Tenant;
use Modules\Users\Models\User;
use Tests\TestCase;

class ConvenioTeleconsultaTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_can_set_own_health_plan_on_profile(): void
    {
        $tenant = Tenant::factory()->create();
        $plan = HealthPlan::query()->create(['tenant_id' => $tenant->id, 'name' => 'Unimed', 'is_active' => true]);

        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole('paciente');
        $patient = Patient::query()->create([
            'tenant_id' => $tenant->id, 'user_id' => $user->id,
            'display_name' => $user->name, 'email' => $user->email,
        ]);

        $this->actingAs($user)->put('/paciente/perfil', ['health_plan_id' => $plan->id])->assertRedirect();

        $this->assertSame($plan->id, $patient->fresh()->health_plan_id);
    }

    public function test_charge_inherits_the_patient_health_plan(): void
    {
        $tenant = Tenant::factory()->create();
        $plan = HealthPlan::query()->create(['tenant_id' => $tenant->id, 'name' => 'SulAmérica', 'is_active' => true]);
        $patient = Patient::query()->create([
            'tenant_id' => $tenant->id, 'display_name' => 'P', 'email' => 'p@example.com',
            'health_plan_id' => $plan->id,
        ]);

        $charges = app(CreateChargeAction::class)($patient, 100.0, CarbonImmutable::now()->addDays(5));

        $this->assertSame($plan->id, $charges->first()->health_plan_id);
    }

    public function test_psychologist_can_set_a_teleconsulta_link_and_patient_sees_it(): void
    {
        $tenant = Tenant::factory()->create();
        $psyUser = User::factory()->create(['tenant_id' => $tenant->id]);
        $psyUser->assignRole('psicologo');
        $psychologist = Psychologist::query()->create([
            'tenant_id' => $tenant->id, 'user_id' => $psyUser->id,
            'professional_registry_encrypted' => 'CRP', 'specialties' => [], 'default_session_duration_minutes' => 50,
        ]);
        $patientUser = User::factory()->create(['tenant_id' => $tenant->id]);
        $patientUser->assignRole('paciente');
        $patient = Patient::query()->create([
            'tenant_id' => $tenant->id, 'user_id' => $patientUser->id,
            'display_name' => $patientUser->name, 'email' => $patientUser->email,
        ]);
        $session = Session::query()->create([
            'tenant_id' => $tenant->id, 'patient_id' => $patient->id, 'psychologist_id' => $psychologist->id,
            'scheduled_at' => CarbonImmutable::now()->addDay(), 'duration_minutes' => 50,
            'modality' => SessionModality::Online, 'status' => SessionStatus::Agendada,
        ]);

        $this->actingAs($psyUser)->post("/sessoes/{$session->id}/teleconsulta", [
            'meeting_url' => 'https://meet.example.com/abc',
        ])->assertRedirect();

        $this->assertSame('https://meet.example.com/abc', $session->fresh()->meeting_url);

        // O paciente vê o link na sua lista de sessões.
        $this->actingAs($patientUser)
            ->get('/minhas-sessoes')
            ->assertInertia(fn ($page) => $page->where('sessions.0.meeting_url', 'https://meet.example.com/abc'));
    }

    public function test_a_stranger_cannot_set_the_teleconsulta_link(): void
    {
        $tenant = Tenant::factory()->create();
        $psyUser = User::factory()->create(['tenant_id' => $tenant->id]);
        $psychologist = Psychologist::query()->create([
            'tenant_id' => $tenant->id, 'user_id' => $psyUser->id,
            'professional_registry_encrypted' => 'CRP', 'specialties' => [], 'default_session_duration_minutes' => 50,
        ]);
        $patient = Patient::query()->create(['tenant_id' => $tenant->id, 'display_name' => 'P', 'email' => 'p@example.com']);
        $session = Session::query()->create([
            'tenant_id' => $tenant->id, 'patient_id' => $patient->id, 'psychologist_id' => $psychologist->id,
            'scheduled_at' => CarbonImmutable::now()->addDay(), 'duration_minutes' => 50,
            'modality' => SessionModality::Online, 'status' => SessionStatus::Agendada,
        ]);

        $intruder = User::factory()->create(['tenant_id' => $tenant->id]);
        $intruder->assignRole('paciente');

        $this->actingAs($intruder)->post("/sessoes/{$session->id}/teleconsulta", [
            'meeting_url' => 'https://evil.example.com',
        ])->assertForbidden();
    }

    public function test_provider_contracts_exist_for_future_integrations(): void
    {
        // Assinatura eletrônica e NFe são contratos (sem implementação/binding ainda).
        $this->assertTrue(interface_exists(SignatureProviderInterface::class));
        $this->assertTrue(interface_exists(InvoiceIssuerInterface::class));
    }
}

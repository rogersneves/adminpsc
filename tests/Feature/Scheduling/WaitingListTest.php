<?php

declare(strict_types=1);

namespace Tests\Feature\Scheduling;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Patients\Models\Patient;
use Modules\Psychologists\Models\Psychologist;
use Modules\Tenant\Models\Tenant;
use Modules\Users\Models\User;
use Tests\TestCase;

class WaitingListTest extends TestCase
{
    use RefreshDatabase;

    private function makePsychologist(): Psychologist
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole('psicologo');

        return Psychologist::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'professional_registry_encrypted' => 'CRP 00/00000',
            'specialties' => [],
            'default_session_duration_minutes' => 50,
        ]);
    }

    private function makePatientUser(string $tenantId): User
    {
        $user = User::factory()->create(['tenant_id' => $tenantId]);
        $user->assignRole('paciente');

        Patient::query()->create([
            'tenant_id' => $tenantId,
            'user_id' => $user->id,
            'display_name' => $user->name,
            'email' => $user->email,
        ]);

        return $user;
    }

    public function test_patient_can_join_the_waiting_list(): void
    {
        $psychologist = $this->makePsychologist();
        $patientUser = $this->makePatientUser($psychologist->tenant_id);

        $response = $this->actingAs($patientUser)->post("/agenda/{$psychologist->id}/lista-espera", [
            'from' => now()->addDays(3)->toDateString(),
            'to' => now()->addDays(10)->toDateString(),
            'notes' => 'Prefiro de manhã',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('waiting_list_entries', [
            'psychologist_id' => $psychologist->id,
        ]);
    }

    public function test_psychologist_can_view_the_waiting_list_but_a_patient_cannot(): void
    {
        $psychologist = $this->makePsychologist();

        $response = $this->actingAs($psychologist->user)->get('/lista-espera');
        $response->assertOk();

        $patientUser = $this->makePatientUser($psychologist->tenant_id);

        $response = $this->actingAs($patientUser)->get('/lista-espera');
        $response->assertForbidden();
    }
}

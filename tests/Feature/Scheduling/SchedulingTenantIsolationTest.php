<?php

declare(strict_types=1);

namespace Tests\Feature\Scheduling;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Psychologists\Models\Psychologist;
use Modules\Tenant\Models\Tenant;
use Modules\Users\Models\User;
use Tests\TestCase;

class SchedulingTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_agenda_index_only_lists_psychologists_from_the_actors_own_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $psychologistUserA = User::factory()->create(['tenant_id' => $tenantA->id, 'name' => 'Psicólogo A']);
        $psychologistUserA->assignRole('psicologo');
        Psychologist::query()->create([
            'tenant_id' => $tenantA->id,
            'user_id' => $psychologistUserA->id,
            'professional_registry_encrypted' => 'CRP 00/00001',
            'specialties' => [],
            'default_session_duration_minutes' => 50,
        ]);

        $psychologistUserB = User::factory()->create(['tenant_id' => $tenantB->id, 'name' => 'Psicólogo B']);
        $psychologistUserB->assignRole('psicologo');
        Psychologist::query()->create([
            'tenant_id' => $tenantB->id,
            'user_id' => $psychologistUserB->id,
            'professional_registry_encrypted' => 'CRP 00/00002',
            'specialties' => [],
            'default_session_duration_minutes' => 50,
        ]);

        $patientUserA = User::factory()->create(['tenant_id' => $tenantA->id]);
        $patientUserA->assignRole('paciente');

        $response = $this->actingAs($patientUserA)->get('/agenda');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('psychologists', 1)
            ->where('psychologists.0.name', 'Psicólogo A'));
    }

    public function test_viewing_a_psychologists_agenda_from_another_tenant_is_not_found(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $psychologistUserB = User::factory()->create(['tenant_id' => $tenantB->id]);
        $psychologistUserB->assignRole('psicologo');
        $psychologistB = Psychologist::query()->create([
            'tenant_id' => $tenantB->id,
            'user_id' => $psychologistUserB->id,
            'professional_registry_encrypted' => 'CRP 00/00002',
            'specialties' => [],
            'default_session_duration_minutes' => 50,
        ]);

        $patientUserA = User::factory()->create(['tenant_id' => $tenantA->id]);
        $patientUserA->assignRole('paciente');

        $response = $this->actingAs($patientUserA)->get("/agenda/{$psychologistB->id}");

        $response->assertNotFound();
    }
}

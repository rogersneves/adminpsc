<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Patients\Models\Patient;
use Modules\Tenant\Models\Tenant;
use Modules\Users\Models\User;
use Tests\TestCase;

/**
 * Primeiro Model de negócio real a usar BelongsToTenant/TenantScope (Fase 1 construiu
 * a trait/scope sem consumidor; Fase 2 é o primeiro teste de integração de verdade).
 *
 * Nota: o ramo "lança exceção fora do console" do TenantScope não é testável via
 * Feature test padrão — `app()->runningInConsole()` do Laravel retorna true durante
 * qualquer execução do PHPUnit (via `isRunningUnitTests()`), então esse ramo sempre
 * cai no bypass de console. Coberto por revisão manual, não por teste automatizado.
 */
class PatientTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_of_one_tenant_cannot_see_a_patient_from_another_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $adminA = User::factory()->create(['tenant_id' => $tenantA->id]);
        $adminA->assignRole('admin_clinica');

        $patientUserB = User::factory()->create(['tenant_id' => $tenantB->id]);
        $patientB = Patient::query()->create([
            'tenant_id' => $tenantB->id,
            'user_id' => $patientUserB->id,
            'display_name' => 'Paciente B',
            'email' => $patientUserB->email,
        ]);

        $this->actingAs($adminA)->get('/dashboard');

        $found = Patient::query()->find($patientB->id);

        $this->assertNull($found);
    }
}

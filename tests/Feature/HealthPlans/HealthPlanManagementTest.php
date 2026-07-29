<?php

declare(strict_types=1);

namespace Tests\Feature\HealthPlans;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Financial\Models\HealthPlan;
use Modules\Tenant\Models\Tenant;
use Modules\Users\Models\User;
use Tests\TestCase;

class HealthPlanManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(Tenant $tenant): User
    {
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole('admin_clinica');

        return $user;
    }

    public function test_admin_can_create_update_and_delete_a_health_plan(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = $this->admin($tenant);

        $this->actingAs($admin)->post('/convenios', ['name' => 'Amil'])->assertRedirect();
        $plan = HealthPlan::query()->withoutTenantScope()->where('tenant_id', $tenant->id)->first();
        $this->assertSame('Amil', $plan->name);
        $this->assertTrue($plan->is_active);

        $this->actingAs($admin)->put("/convenios/{$plan->id}", ['name' => 'Amil Saúde', 'is_active' => false])->assertRedirect();
        $this->assertSame('Amil Saúde', $plan->fresh()->name);
        $this->assertFalse($plan->fresh()->is_active);

        $this->actingAs($admin)->delete("/convenios/{$plan->id}")->assertRedirect();
        $this->assertSoftDeleted('health_plans', ['id' => $plan->id]);
    }

    public function test_admin_cannot_touch_a_plan_from_another_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $adminA = $this->admin($tenantA);
        $tenantB = Tenant::factory()->create();
        $planB = HealthPlan::query()->create(['tenant_id' => $tenantB->id, 'name' => 'B', 'is_active' => true]);

        $this->actingAs($adminA)->put("/convenios/{$planB->id}", ['name' => 'hack', 'is_active' => true])->assertNotFound();
    }

    public function test_psychologist_cannot_manage_health_plans(): void
    {
        $tenant = Tenant::factory()->create();
        $psychologist = User::factory()->create(['tenant_id' => $tenant->id]);
        $psychologist->assignRole('psicologo');

        $this->actingAs($psychologist)->get('/convenios')->assertForbidden();
    }
}

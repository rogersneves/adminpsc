<?php

declare(strict_types=1);

namespace Tests\Feature\Units;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Settings\Models\Unit;
use Modules\Tenant\Models\Tenant;
use Modules\Users\Models\User;
use Tests\TestCase;

class UnitManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(Tenant $tenant): User
    {
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole('admin_clinica');

        return $user;
    }

    public function test_admin_can_create_a_unit(): void
    {
        $tenant = Tenant::factory()->create();

        $this->actingAs($this->admin($tenant))->post('/unidades', [
            'name' => 'Unidade Centro',
            'city' => 'São Paulo',
        ])->assertRedirect();

        $this->assertDatabaseHas('units', [
            'tenant_id' => $tenant->id,
            'name' => 'Unidade Centro',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_update_and_delete_a_unit(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = $this->admin($tenant);
        $unit = Unit::query()->create(['tenant_id' => $tenant->id, 'name' => 'A', 'is_active' => true]);

        $this->actingAs($admin)->put("/unidades/{$unit->id}", [
            'name' => 'A renomeada', 'city' => 'Rio', 'is_active' => false,
        ])->assertRedirect();

        $this->assertDatabaseHas('units', ['id' => $unit->id, 'name' => 'A renomeada', 'is_active' => false]);

        $this->actingAs($admin)->delete("/unidades/{$unit->id}")->assertRedirect();
        $this->assertSoftDeleted('units', ['id' => $unit->id]);
    }

    public function test_admin_cannot_touch_a_unit_from_another_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $adminA = $this->admin($tenantA);

        $tenantB = Tenant::factory()->create();
        $unitB = Unit::query()->create(['tenant_id' => $tenantB->id, 'name' => 'B', 'is_active' => true]);

        $this->actingAs($adminA)->put("/unidades/{$unitB->id}", [
            'name' => 'hack', 'city' => '', 'is_active' => true,
        ])->assertNotFound();
    }

    public function test_psychologist_cannot_manage_units(): void
    {
        $tenant = Tenant::factory()->create();
        $psychologist = User::factory()->create(['tenant_id' => $tenant->id]);
        $psychologist->assignRole('psicologo');

        $this->actingAs($psychologist)->get('/unidades')->assertForbidden();
    }
}

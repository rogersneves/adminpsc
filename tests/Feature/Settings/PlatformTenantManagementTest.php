<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tenant\Models\Tenant;
use Modules\Users\Models\User;
use Tests\TestCase;

class PlatformTenantManagementTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        // Super Admin da plataforma não tem tenant (docs/01-Arquitetura.md).
        $user = User::factory()->create(['tenant_id' => null]);
        $user->assignRole('super_admin');

        return $user;
    }

    public function test_super_admin_can_list_tenants(): void
    {
        Tenant::factory()->create(['name' => 'Clínica X']);

        $this->actingAs($this->superAdmin())
            ->get('/plataforma/tenants')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Platform/Tenants')->has('tenants'));
    }

    public function test_super_admin_can_provision_a_tenant(): void
    {
        $this->actingAs($this->superAdmin())->post('/plataforma/tenants', [
            'name' => 'Provisionada',
            'plan' => 'basico',
        ])->assertRedirect();

        $tenant = Tenant::query()->where('name', 'Provisionada')->first();
        $this->assertNotNull($tenant);
        $this->assertSame('basico', $tenant->plan);
        $this->assertNotNull($tenant->trial_ends_at);
    }

    public function test_super_admin_can_change_plan_and_status(): void
    {
        $tenant = Tenant::factory()->create(['plan' => 'trial', 'status' => 'active']);

        $this->actingAs($this->superAdmin())->put("/plataforma/tenants/{$tenant->id}", [
            'plan' => 'profissional',
            'status' => 'suspended',
        ])->assertRedirect();

        $tenant->refresh();
        $this->assertSame('profissional', $tenant->plan);
        $this->assertSame('suspended', $tenant->status);
    }

    public function test_clinic_admin_cannot_access_platform_tenant_management(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->create(['tenant_id' => $tenant->id]);
        $admin->assignRole('admin_clinica');

        $this->actingAs($admin)->get('/plataforma/tenants')->assertForbidden();
    }
}

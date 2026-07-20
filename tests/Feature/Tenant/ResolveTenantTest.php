<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tenant\Models\Tenant;
use Modules\Users\Models\User;
use Tests\TestCase;

class ResolveTenantTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_the_tenant_of_the_authenticated_user(): void
    {
        $tenant = Tenant::factory()->create(['name' => 'Clínica Teste']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('tenant.name', 'Clínica Teste'));
    }

    public function test_super_admin_without_tenant_sees_no_tenant_resolved(): void
    {
        $superAdmin = User::factory()->create(['tenant_id' => null]);

        $response = $this->actingAs($superAdmin)->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('tenant', null));
    }
}

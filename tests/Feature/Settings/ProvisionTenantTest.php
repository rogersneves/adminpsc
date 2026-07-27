<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Settings\Actions\ProvisionTenantAction;
use Modules\Tenant\Models\Tenant;
use Tests\TestCase;

class ProvisionTenantTest extends TestCase
{
    use RefreshDatabase;

    public function test_provisioning_sets_default_plan_and_trial_window(): void
    {
        config(['plans.default' => 'trial', 'plans.trial_days' => 14]);

        $tenant = app(ProvisionTenantAction::class)('Clínica Nova');

        $this->assertSame('trial', $tenant->plan);
        $this->assertSame('active', $tenant->status);
        $this->assertTrue($tenant->onTrial());
        $this->assertNotNull($tenant->trial_ends_at);
    }

    public function test_provisioning_generates_unique_slugs(): void
    {
        $a = app(ProvisionTenantAction::class)('Mesmo Nome');
        $b = app(ProvisionTenantAction::class)('Mesmo Nome');

        $this->assertNotSame($a->slug, $b->slug);
    }

    public function test_clinic_admin_registration_provisions_a_trial_tenant(): void
    {
        $this->post('/register', [
            'tenant_name' => 'Clínica Registro',
            'name' => 'Dono',
            'email' => 'dono@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $tenant = Tenant::query()->where('name', 'Clínica Registro')->first();
        $this->assertNotNull($tenant);
        $this->assertSame('trial', $tenant->plan);
        $this->assertNotNull($tenant->trial_ends_at);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Settings\Services\TenantSettings;
use Modules\Tenant\Models\Tenant;
use Modules\Users\Models\User;
use Tests\TestCase;

class TenantSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_unset_key_falls_back_to_global_config(): void
    {
        config(['scheduling.booking_horizon_days' => 30]);
        $tenant = Tenant::factory()->create(['settings' => []]);

        $this->assertSame(30, app(TenantSettings::class)->get($tenant, 'scheduling.booking_horizon_days'));
    }

    public function test_stored_value_overrides_config(): void
    {
        config(['scheduling.booking_horizon_days' => 30]);
        $tenant = Tenant::factory()->create([
            'settings' => ['scheduling' => ['booking_horizon_days' => 7]],
        ]);

        $this->assertSame(7, app(TenantSettings::class)->get($tenant, 'scheduling.booking_horizon_days'));
    }

    public function test_set_persists_only_known_keys(): void
    {
        $tenant = Tenant::factory()->create(['settings' => []]);

        app(TenantSettings::class)->set($tenant, [
            'scheduling.minimum_reschedule_notice_hours' => 48,
            'unknown.key' => 'ignored',
        ]);

        $tenant->refresh();
        $this->assertSame(48, data_get($tenant->settings, 'scheduling.minimum_reschedule_notice_hours'));
        $this->assertNull(data_get($tenant->settings, 'unknown.key'));
    }

    public function test_branding_display_name_defaults_to_tenant_name(): void
    {
        $tenant = Tenant::factory()->create(['name' => 'Clínica Alfa', 'settings' => []]);

        $this->assertSame('Clínica Alfa', app(TenantSettings::class)->get($tenant, 'branding.display_name'));
    }

    public function test_admin_can_view_and_update_settings(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->create(['tenant_id' => $tenant->id]);
        $admin->assignRole('admin_clinica');

        $this->actingAs($admin)->get('/configuracoes')->assertOk();

        $this->actingAs($admin)->put('/configuracoes', [
            'scheduling' => ['booking_horizon_days' => 15, 'minimum_reschedule_notice_hours' => 12],
            'branding' => ['display_name' => 'Nova Marca', 'primary_color' => '#123456'],
        ])->assertRedirect();

        $tenant->refresh();
        $this->assertSame(15, data_get($tenant->settings, 'scheduling.booking_horizon_days'));
        $this->assertSame('Nova Marca', data_get($tenant->settings, 'branding.display_name'));
        $this->assertSame('#123456', data_get($tenant->settings, 'branding.primary_color'));
    }

    public function test_invalid_color_is_rejected(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->create(['tenant_id' => $tenant->id]);
        $admin->assignRole('admin_clinica');

        $this->actingAs($admin)->put('/configuracoes', [
            'scheduling' => ['booking_horizon_days' => 15, 'minimum_reschedule_notice_hours' => 12],
            'branding' => ['display_name' => 'X', 'primary_color' => 'notacolor'],
        ])->assertSessionHasErrors('branding.primary_color');
    }

    public function test_user_without_manage_clinic_settings_is_forbidden(): void
    {
        $tenant = Tenant::factory()->create();
        $psychologist = User::factory()->create(['tenant_id' => $tenant->id]);
        $psychologist->assignRole('psicologo');

        $this->actingAs($psychologist)->get('/configuracoes')->assertForbidden();
    }
}

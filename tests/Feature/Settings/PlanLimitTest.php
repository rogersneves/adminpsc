<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Modules\Psychologists\Models\Psychologist;
use Modules\Settings\Services\PlanLimits;
use Modules\Tenant\Models\Tenant;
use Modules\Users\Models\User;
use Tests\TestCase;

class PlanLimitTest extends TestCase
{
    use RefreshDatabase;

    private function fillPsychologists(Tenant $tenant, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $user = User::factory()->create(['tenant_id' => $tenant->id]);
            Psychologist::query()->create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'professional_registry_encrypted' => 'CRP '.$i,
                'specialties' => [],
                'default_session_duration_minutes' => 50,
            ]);
        }
    }

    public function test_can_add_is_false_at_the_plan_limit(): void
    {
        $tenant = Tenant::factory()->create(['plan' => 'trial']); // max 2
        $this->fillPsychologists($tenant, 2);

        $this->assertFalse(app(PlanLimits::class)->canAddPsychologist($tenant));
    }

    public function test_unlimited_plan_always_allows(): void
    {
        $tenant = Tenant::factory()->create(['plan' => 'profissional']); // ilimitado
        $this->fillPsychologists($tenant, 10);

        $this->assertTrue(app(PlanLimits::class)->canAddPsychologist($tenant));
    }

    public function test_creating_beyond_the_limit_is_blocked_with_a_validation_error(): void
    {
        Notification::fake();

        $tenant = Tenant::factory()->create(['plan' => 'trial']); // max 2
        $this->fillPsychologists($tenant, 2);

        $admin = User::factory()->create(['tenant_id' => $tenant->id]);
        $admin->assignRole('admin_clinica');

        $response = $this->actingAs($admin)->post('/psicologos', [
            'name' => 'Novo Psi',
            'email' => 'novo@example.com',
            'professional_registry' => 'CRP 99',
        ]);

        $response->assertSessionHasErrors('plan');
        $this->assertDatabaseMissing('users', ['email' => 'novo@example.com']);
    }

    public function test_creating_within_the_limit_succeeds(): void
    {
        Notification::fake();

        $tenant = Tenant::factory()->create(['plan' => 'trial']); // max 2
        $this->fillPsychologists($tenant, 1);

        $admin = User::factory()->create(['tenant_id' => $tenant->id]);
        $admin->assignRole('admin_clinica');

        $this->actingAs($admin)->post('/psicologos', [
            'name' => 'Segundo Psi',
            'email' => 'segundo@example.com',
            'professional_registry' => 'CRP 02',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', ['email' => 'segundo@example.com']);
    }
}

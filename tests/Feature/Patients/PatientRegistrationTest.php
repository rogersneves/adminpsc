<?php

declare(strict_types=1);

namespace Tests\Feature\Patients;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Modules\Patients\Models\Patient;
use Modules\Tenant\Models\Tenant;
use Modules\Users\Models\User;
use Tests\TestCase;

class PatientRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registering_under_a_tenant_slug_creates_user_and_patient(): void
    {
        Notification::fake();

        $tenant = Tenant::factory()->create(['slug' => 'clinica-bem-estar', 'status' => 'active']);

        $response = $this->post("/c/{$tenant->slug}/paciente/registro", [
            'display_name' => 'Paciente Teste',
            'email' => 'paciente@example.com',
            'password' => 'Segredo-Forte-123',
            'password_confirmation' => 'Segredo-Forte-123',
        ]);

        $response->assertRedirect('/verify-email');

        $user = User::query()->where('email', 'paciente@example.com')->firstOrFail();
        $patient = Patient::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame($tenant->id, $user->tenant_id);
        $this->assertSame($tenant->id, $patient->tenant_id);
        $this->assertTrue($user->hasRole('paciente'));
        $this->assertNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);
    }

    public function test_registering_under_an_unknown_slug_is_a_404(): void
    {
        $response = $this->get('/c/clinica-que-nao-existe/paciente/registro');

        $response->assertNotFound();
    }

    public function test_registering_under_an_inactive_tenant_is_a_404(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'clinica-inativa', 'status' => 'inactive']);

        $response = $this->get("/c/{$tenant->slug}/paciente/registro");

        $response->assertNotFound();
    }
}

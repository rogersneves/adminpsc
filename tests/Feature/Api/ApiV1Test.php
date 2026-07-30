<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Patients\Models\Patient;
use Modules\Psychologists\Models\Psychologist;
use Modules\Scheduling\Models\Session;
use Modules\Tenant\Models\Tenant;
use Modules\Users\Models\User;
use Tests\TestCase;

/**
 * API pública v1 (marco). Usa tokens Sanctum reais (Bearer), exercitando de ponta a
 * ponta a emissão de token + a migration uuidMorphs de personal_access_tokens.
 */
class ApiV1Test extends TestCase
{
    use RefreshDatabase;

    private function tokenHeaders(User $user, string $name = 'test'): array
    {
        $token = $user->createToken($name)->plainTextToken;

        return ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];
    }

    private function makePsychologist(Tenant $tenant): Psychologist
    {
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole('psicologo');

        return Psychologist::query()->create([
            'tenant_id' => $tenant->id, 'user_id' => $user->id,
            'professional_registry_encrypted' => 'CRP', 'specialties' => ['TCC'],
            'default_session_duration_minutes' => 50,
        ]);
    }

    private function makePatientUser(Tenant $tenant): array
    {
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole('paciente');
        $patient = Patient::query()->create([
            'tenant_id' => $tenant->id, 'user_id' => $user->id,
            'display_name' => $user->name, 'email' => $user->email,
        ]);

        return [$user, $patient];
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_me_returns_the_token_owner_and_tenant(): void
    {
        $tenant = Tenant::factory()->create(['name' => 'Clínica X']);
        [$user] = $this->makePatientUser($tenant);

        $this->withHeaders($this->tokenHeaders($user))
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.tenant.name', 'Clínica X')
            ->assertJsonPath('data.roles.0', 'paciente');
    }

    public function test_psychologists_are_scoped_to_the_token_owner_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $this->makePsychologist($tenantA);
        [$userA] = $this->makePatientUser($tenantA);

        $tenantB = Tenant::factory()->create();
        $this->makePsychologist($tenantB);
        $this->makePsychologist($tenantB);

        $this->withHeaders($this->tokenHeaders($userA))
            ->getJson('/api/v1/psychologists')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_patient_can_book_a_session_through_the_api(): void
    {
        $tenant = Tenant::factory()->create();
        $psychologist = $this->makePsychologist($tenant);
        [$user, $patient] = $this->makePatientUser($tenant);

        $psychologist->availabilities()->create([
            'tenant_id' => $tenant->id, 'type' => 'recorrente',
            'weekday' => CarbonImmutable::now()->next()->dayOfWeek,
            'start_time' => '09:00', 'end_time' => '10:00', 'buffer_minutes' => 0,
        ]);
        $slotStart = CarbonImmutable::now()->next()->setTime(9, 0);

        $this->withHeaders($this->tokenHeaders($user))
            ->postJson('/api/v1/sessions', [
                'psychologist_id' => $psychologist->id,
                'scheduled_at' => $slotStart->toIso8601String(),
                'duration_minutes' => 50,
                'modality' => 'online',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'agendada');

        $this->assertDatabaseHas('clinical_sessions', [
            'patient_id' => $patient->id,
            'psychologist_id' => $psychologist->id,
        ]);
    }

    public function test_double_booking_through_api_returns_422(): void
    {
        $tenant = Tenant::factory()->create();
        $psychologist = $this->makePsychologist($tenant);
        [$user, $patient] = $this->makePatientUser($tenant);
        $slotStart = CarbonImmutable::now()->next()->setTime(9, 0);
        Session::query()->create([
            'tenant_id' => $tenant->id, 'patient_id' => $patient->id, 'psychologist_id' => $psychologist->id,
            'scheduled_at' => $slotStart, 'duration_minutes' => 50, 'modality' => 'online', 'status' => 'agendada',
        ]);

        $this->withHeaders($this->tokenHeaders($user))
            ->postJson('/api/v1/sessions', [
                'psychologist_id' => $psychologist->id,
                'scheduled_at' => $slotStart->toIso8601String(),
                'duration_minutes' => 50,
                'modality' => 'online',
            ])
            ->assertStatus(422);
    }

    public function test_revoked_token_no_longer_authenticates(): void
    {
        $tenant = Tenant::factory()->create();
        [$user] = $this->makePatientUser($tenant);
        $headers = $this->tokenHeaders($user);

        $this->withHeaders($headers)->getJson('/api/v1/me')->assertOk();

        $user->tokens()->delete();

        // O RequestGuard do Sanctum cacheia o usuário resolvido dentro do mesmo
        // processo de teste; em produção cada request é isolado. Força a re-resolução
        // para simular uma requisição nova com o token já revogado.
        $this->app['auth']->forgetGuards();

        $this->withHeaders($headers)->getJson('/api/v1/me')->assertUnauthorized();
    }
}

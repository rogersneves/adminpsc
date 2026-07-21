<?php

declare(strict_types=1);

namespace Tests\Feature\Scheduling;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Patients\Models\Patient;
use Modules\Psychologists\Models\Psychologist;
use Modules\Scheduling\Models\Session;
use Modules\Tenant\Models\Tenant;
use Modules\Users\Models\User;
use Tests\TestCase;

class BookSessionTest extends TestCase
{
    use RefreshDatabase;

    private function makePsychologist(?Tenant $tenant = null): Psychologist
    {
        $tenant ??= Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole('psicologo');

        return Psychologist::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'professional_registry_encrypted' => 'CRP 00/00000',
            'specialties' => [],
            'default_session_duration_minutes' => 50,
        ]);
    }

    private function makePatientUser(string $tenantId): array
    {
        $user = User::factory()->create(['tenant_id' => $tenantId]);
        $user->assignRole('paciente');

        $patient = Patient::query()->create([
            'tenant_id' => $tenantId,
            'user_id' => $user->id,
            'display_name' => $user->name,
            'email' => $user->email,
        ]);

        return [$user, $patient];
    }

    public function test_patient_can_book_an_available_slot(): void
    {
        $psychologist = $this->makePsychologist();
        [$user, $patient] = $this->makePatientUser($psychologist->tenant_id);

        $psychologist->availabilities()->create([
            'tenant_id' => $psychologist->tenant_id,
            'type' => 'recorrente',
            'weekday' => CarbonImmutable::now()->next()->dayOfWeek,
            'start_time' => '09:00',
            'end_time' => '10:00',
            'buffer_minutes' => 0,
        ]);

        $slotStart = CarbonImmutable::now()->next()->setTime(9, 0);

        $response = $this->actingAs($user)->post("/agenda/{$psychologist->id}/reservar", [
            'scheduled_at' => $slotStart->toIso8601String(),
            'duration_minutes' => 50,
            'modality' => 'online',
        ]);

        $response->assertRedirect('/minhas-sessoes');
        $this->assertDatabaseHas('clinical_sessions', [
            'patient_id' => $patient->id,
            'psychologist_id' => $psychologist->id,
            'status' => 'agendada',
        ]);
    }

    public function test_booking_the_same_slot_twice_fails_the_second_time(): void
    {
        $psychologist = $this->makePsychologist();
        [$userA, $patientA] = $this->makePatientUser($psychologist->tenant_id);
        [$userB, $patientB] = $this->makePatientUser($psychologist->tenant_id);

        $weekday = CarbonImmutable::now()->next()->dayOfWeek;
        $psychologist->availabilities()->create([
            'tenant_id' => $psychologist->tenant_id,
            'type' => 'recorrente',
            'weekday' => $weekday,
            'start_time' => '09:00',
            'end_time' => '10:00',
            'buffer_minutes' => 0,
        ]);

        $slotStart = CarbonImmutable::now()->next()->setTime(9, 0);
        $payload = [
            'scheduled_at' => $slotStart->toIso8601String(),
            'duration_minutes' => 50,
            'modality' => 'online',
        ];

        $first = $this->actingAs($userA)->post("/agenda/{$psychologist->id}/reservar", $payload);
        $first->assertRedirect('/minhas-sessoes');

        $second = $this->actingAs($userB)->post("/agenda/{$psychologist->id}/reservar", $payload);
        $second->assertSessionHasErrors('scheduled_at');

        $this->assertSame(1, Session::query()->where('psychologist_id', $psychologist->id)->count());
    }

    public function test_patient_from_another_tenant_cannot_book_with_this_psychologist(): void
    {
        $psychologist = $this->makePsychologist();
        $otherTenant = Tenant::factory()->create();
        [$outsideUser] = $this->makePatientUser($otherTenant->id);

        $weekday = CarbonImmutable::now()->next()->dayOfWeek;
        $psychologist->availabilities()->create([
            'tenant_id' => $psychologist->tenant_id,
            'type' => 'recorrente',
            'weekday' => $weekday,
            'start_time' => '09:00',
            'end_time' => '10:00',
            'buffer_minutes' => 0,
        ]);

        $slotStart = CarbonImmutable::now()->next()->setTime(9, 0);

        $response = $this->actingAs($outsideUser)->post("/agenda/{$psychologist->id}/reservar", [
            'scheduled_at' => $slotStart->toIso8601String(),
            'duration_minutes' => 50,
            'modality' => 'online',
        ]);

        $response->assertNotFound();
    }
}

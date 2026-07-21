<?php

declare(strict_types=1);

namespace Tests\Feature\Scheduling;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Patients\Models\Patient;
use Modules\Psychologists\Models\Psychologist;
use Modules\Scheduling\Enums\SessionModality;
use Modules\Scheduling\Enums\SessionStatus;
use Modules\Scheduling\Models\Session;
use Modules\Tenant\Models\Tenant;
use Modules\Users\Models\User;
use Tests\TestCase;

class CancelRescheduleSessionTest extends TestCase
{
    use RefreshDatabase;

    private function makeBookedSession(CarbonImmutable $scheduledAt): array
    {
        $tenant = Tenant::factory()->create();
        $psychologistUser = User::factory()->create(['tenant_id' => $tenant->id]);
        $psychologistUser->assignRole('psicologo');
        $psychologist = Psychologist::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $psychologistUser->id,
            'professional_registry_encrypted' => 'CRP 00/00000',
            'specialties' => [],
            'default_session_duration_minutes' => 50,
        ]);

        $patientUser = User::factory()->create(['tenant_id' => $tenant->id]);
        $patientUser->assignRole('paciente');
        $patient = Patient::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $patientUser->id,
            'display_name' => $patientUser->name,
            'email' => $patientUser->email,
        ]);

        $session = Session::query()->create([
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
            'psychologist_id' => $psychologist->id,
            'scheduled_at' => $scheduledAt,
            'duration_minutes' => 50,
            'modality' => SessionModality::Online,
            'status' => SessionStatus::Agendada,
        ]);

        return compact('session', 'patientUser', 'psychologistUser', 'psychologist');
    }

    public function test_cancelling_less_than_24_hours_before_the_session_is_rejected(): void
    {
        ['session' => $session, 'patientUser' => $patientUser] = $this->makeBookedSession(
            CarbonImmutable::now()->addHours(5),
        );

        $response = $this->actingAs($patientUser)->post("/sessoes/{$session->id}/cancelar");

        $response->assertSessionHasErrors('session');
        $this->assertSame(SessionStatus::Agendada, $session->fresh()->status);
    }

    public function test_cancelling_24_hours_or_more_before_the_session_succeeds(): void
    {
        ['session' => $session, 'patientUser' => $patientUser] = $this->makeBookedSession(
            CarbonImmutable::now()->addHours(48),
        );

        $response = $this->actingAs($patientUser)->post("/sessoes/{$session->id}/cancelar");

        $response->assertRedirect();
        $this->assertSame(SessionStatus::Cancelada, $session->fresh()->status);
    }

    public function test_rescheduling_creates_a_new_session_linked_to_the_old_one(): void
    {
        [
            'session' => $session,
            'patientUser' => $patientUser,
            'psychologist' => $psychologist,
        ] = $this->makeBookedSession(CarbonImmutable::now()->addHours(48));

        $newTime = CarbonImmutable::now()->addDays(5)->setTime(14, 0);

        $psychologist->availabilities()->create([
            'tenant_id' => $psychologist->tenant_id,
            'type' => 'recorrente',
            'weekday' => $newTime->dayOfWeek,
            'start_time' => '14:00',
            'end_time' => '18:00',
            'buffer_minutes' => 0,
        ]);

        $response = $this->actingAs($patientUser)->post("/sessoes/{$session->id}/reagendar", [
            'scheduled_at' => $newTime->toIso8601String(),
            'duration_minutes' => 50,
        ]);

        $response->assertRedirect();
        $response->assertSessionDoesntHaveErrors();

        $session->refresh();
        $this->assertSame(SessionStatus::Reagendada, $session->status);

        $newSession = Session::query()->where('rescheduled_from_id', $session->id)->first();
        $this->assertNotNull($newSession);
        $this->assertSame($psychologist->id, $newSession->psychologist_id);
        $this->assertSame(SessionStatus::Agendada, $newSession->status);
    }

    public function test_another_patient_in_the_same_clinic_cannot_cancel_a_session_that_is_not_theirs(): void
    {
        ['session' => $session] = $this->makeBookedSession(CarbonImmutable::now()->addHours(48));

        // mesmo tenant do paciente/psicólogo criados em makeBookedSession, mas sem
        // nenhuma relação com essa sessão especificamente
        $stranger = User::factory()->create(['tenant_id' => $session->tenant_id]);
        $stranger->assignRole('paciente');

        $response = $this->actingAs($stranger)->post("/sessoes/{$session->id}/cancelar");

        $response->assertForbidden();
    }
}

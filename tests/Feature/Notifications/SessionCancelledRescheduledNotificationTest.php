<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Modules\Notifications\Notifications\SessionCancelledNotification;
use Modules\Notifications\Notifications\SessionRescheduledNotification;
use Modules\Patients\Models\Patient;
use Modules\Psychologists\Models\Psychologist;
use Modules\Scheduling\Enums\SessionModality;
use Modules\Scheduling\Enums\SessionStatus;
use Modules\Scheduling\Models\Session;
use Modules\Tenant\Models\Tenant;
use Modules\Users\Models\User;
use Tests\TestCase;

class SessionCancelledRescheduledNotificationTest extends TestCase
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

    public function test_cancelling_a_session_notifies_both_patient_and_psychologist(): void
    {
        Notification::fake();

        ['session' => $session, 'patientUser' => $patientUser, 'psychologistUser' => $psychologistUser] =
            $this->makeBookedSession(CarbonImmutable::now()->addHours(48));

        $this->actingAs($patientUser)->post("/sessoes/{$session->id}/cancelar")->assertRedirect();

        Notification::assertSentTo($patientUser, SessionCancelledNotification::class);
        Notification::assertSentTo($psychologistUser, SessionCancelledNotification::class);
    }

    public function test_rescheduling_a_session_notifies_both_patient_and_psychologist(): void
    {
        Notification::fake();

        [
            'session' => $session,
            'patientUser' => $patientUser,
            'psychologistUser' => $psychologistUser,
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

        $this->actingAs($patientUser)->post("/sessoes/{$session->id}/reagendar", [
            'scheduled_at' => $newTime->toIso8601String(),
            'duration_minutes' => 50,
        ])->assertRedirect();

        Notification::assertSentTo($patientUser, SessionRescheduledNotification::class);
        Notification::assertSentTo($psychologistUser, SessionRescheduledNotification::class);
    }
}

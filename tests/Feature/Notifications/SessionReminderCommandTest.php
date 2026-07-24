<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Modules\Notifications\Notifications\SessionReminderNotification;
use Modules\Patients\Models\Patient;
use Modules\Psychologists\Models\Psychologist;
use Modules\Scheduling\Enums\SessionModality;
use Modules\Scheduling\Enums\SessionStatus;
use Modules\Scheduling\Models\Session;
use Modules\Tenant\Models\Tenant;
use Modules\Users\Models\User;
use Tests\TestCase;

class SessionReminderCommandTest extends TestCase
{
    use RefreshDatabase;

    private function makeSession(CarbonImmutable $scheduledAt, SessionStatus $status, ?CarbonImmutable $reminderSentAt = null): array
    {
        $tenant = Tenant::factory()->create();

        $psychologistUser = User::factory()->create(['tenant_id' => $tenant->id]);
        $psychologist = Psychologist::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $psychologistUser->id,
            'professional_registry_encrypted' => 'CRP 00/00000',
            'specialties' => [],
            'default_session_duration_minutes' => 50,
        ]);

        $patientUser = User::factory()->create(['tenant_id' => $tenant->id]);
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
            'status' => $status,
            'reminder_sent_at' => $reminderSentAt,
        ]);

        return compact('session', 'patientUser');
    }

    public function test_sends_reminder_for_a_session_within_the_configured_window(): void
    {
        Notification::fake();

        ['session' => $session, 'patientUser' => $patientUser] = $this->makeSession(
            CarbonImmutable::now()->addHours(12),
            SessionStatus::Agendada,
        );

        $this->artisan('notifications:send-session-reminders')->assertSuccessful();

        Notification::assertSentTo($patientUser, SessionReminderNotification::class);
        $this->assertNotNull($session->fresh()->reminder_sent_at);
    }

    public function test_does_not_send_reminder_for_a_session_outside_the_window(): void
    {
        Notification::fake();

        ['patientUser' => $patientUser] = $this->makeSession(
            CarbonImmutable::now()->addHours(48),
            SessionStatus::Agendada,
        );

        $this->artisan('notifications:send-session-reminders')->assertSuccessful();

        Notification::assertNotSentTo($patientUser, SessionReminderNotification::class);
    }

    public function test_does_not_send_a_second_reminder_for_the_same_session(): void
    {
        Notification::fake();

        ['patientUser' => $patientUser] = $this->makeSession(
            CarbonImmutable::now()->addHours(12),
            SessionStatus::Agendada,
            CarbonImmutable::now()->subHour(),
        );

        $this->artisan('notifications:send-session-reminders')->assertSuccessful();

        Notification::assertNotSentTo($patientUser, SessionReminderNotification::class);
    }

    public function test_does_not_send_reminder_for_a_cancelled_session(): void
    {
        Notification::fake();

        ['patientUser' => $patientUser] = $this->makeSession(
            CarbonImmutable::now()->addHours(12),
            SessionStatus::Cancelada,
        );

        $this->artisan('notifications:send-session-reminders')->assertSuccessful();

        Notification::assertNotSentTo($patientUser, SessionReminderNotification::class);
    }
}

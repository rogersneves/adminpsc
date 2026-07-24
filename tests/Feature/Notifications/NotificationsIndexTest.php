<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Notifications\Notifications\SessionReminderNotification;
use Modules\Patients\Models\Patient;
use Modules\Psychologists\Models\Psychologist;
use Modules\Scheduling\Enums\SessionModality;
use Modules\Scheduling\Enums\SessionStatus;
use Modules\Scheduling\Models\Session;
use Modules\Tenant\Models\Tenant;
use Modules\Users\Models\User;
use Tests\TestCase;

class NotificationsIndexTest extends TestCase
{
    use RefreshDatabase;

    private function makeSessionForPatient(User $patientUser, Patient $patient, Tenant $tenant): Session
    {
        $psychologistUser = User::factory()->create(['tenant_id' => $tenant->id]);
        $psychologist = Psychologist::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $psychologistUser->id,
            'professional_registry_encrypted' => 'CRP 00/00000',
            'specialties' => [],
            'default_session_duration_minutes' => 50,
        ]);

        return Session::query()->create([
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
            'psychologist_id' => $psychologist->id,
            'scheduled_at' => now()->addDay(),
            'duration_minutes' => 50,
            'modality' => SessionModality::Online,
            'status' => SessionStatus::Agendada,
        ]);
    }

    private function makePatientWithNotification(): array
    {
        $tenant = Tenant::factory()->create();

        $patientUser = User::factory()->create(['tenant_id' => $tenant->id]);
        $patient = Patient::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $patientUser->id,
            'display_name' => $patientUser->name,
            'email' => $patientUser->email,
        ]);

        $session = $this->makeSessionForPatient($patientUser, $patient, $tenant);
        $patientUser->notify(new SessionReminderNotification($session));

        return [$patientUser, $patientUser->notifications()->sole()];
    }

    public function test_user_sees_their_own_notifications(): void
    {
        [$patientUser] = $this->makePatientWithNotification();

        $response = $this->actingAs($patientUser)->get('/notificacoes');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Notifications/Index')
            ->has('notifications.data', 1)
            ->where('notifications.data.0.title', 'Lembrete de sessão'));
    }

    public function test_marking_a_notification_as_read(): void
    {
        [$patientUser, $notification] = $this->makePatientWithNotification();

        $this->assertNull($notification->read_at);

        $this->actingAs($patientUser)
            ->patch("/notificacoes/{$notification->id}/lida")
            ->assertRedirect();

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_marking_all_notifications_as_read(): void
    {
        [$patientUser] = $this->makePatientWithNotification();

        $this->actingAs($patientUser)
            ->post('/notificacoes/marcar-todas-lidas')
            ->assertRedirect();

        $this->assertSame(0, $patientUser->unreadNotifications()->count());
    }

    public function test_a_user_cannot_mark_another_users_notification_as_read(): void
    {
        [, $notification] = $this->makePatientWithNotification();

        $tenant = Tenant::factory()->create();
        $stranger = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($stranger)
            ->patch("/notificacoes/{$notification->id}/lida")
            ->assertForbidden();

        $this->assertNull($notification->fresh()->read_at);
    }
}

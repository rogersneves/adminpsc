<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Modules\Financial\Enums\ChargeStatus;
use Modules\Financial\Models\FinancialCharge;
use Modules\Notifications\Notifications\ChargeDueSoonNotification;
use Modules\Notifications\Notifications\ChargeOverdueNotification;
use Modules\Patients\Models\Patient;
use Modules\Tenant\Models\Tenant;
use Modules\Users\Models\User;
use Tests\TestCase;

class ChargeReminderCommandTest extends TestCase
{
    use RefreshDatabase;

    private function makePatient(): array
    {
        $tenant = Tenant::factory()->create();

        $patientUser = User::factory()->create(['tenant_id' => $tenant->id]);
        $patient = Patient::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $patientUser->id,
            'display_name' => $patientUser->name,
            'email' => $patientUser->email,
        ]);

        return [$patient, $patientUser];
    }

    public function test_sends_due_soon_reminder_within_the_configured_window(): void
    {
        Notification::fake();

        [$patient, $patientUser] = $this->makePatient();

        $charge = FinancialCharge::query()->create([
            'tenant_id' => $patient->tenant_id,
            'patient_id' => $patient->id,
            'amount' => 100,
            'due_date' => now()->addDays(2),
            'status' => ChargeStatus::EmAberto,
        ]);

        $this->artisan('notifications:send-charge-reminders')->assertSuccessful();

        Notification::assertSentTo($patientUser, ChargeDueSoonNotification::class);
        $this->assertNotNull($charge->fresh()->due_soon_reminder_sent_at);
    }

    public function test_does_not_send_due_soon_reminder_far_from_due_date(): void
    {
        Notification::fake();

        [$patient, $patientUser] = $this->makePatient();

        FinancialCharge::query()->create([
            'tenant_id' => $patient->tenant_id,
            'patient_id' => $patient->id,
            'amount' => 100,
            'due_date' => now()->addDays(20),
            'status' => ChargeStatus::EmAberto,
        ]);

        $this->artisan('notifications:send-charge-reminders')->assertSuccessful();

        Notification::assertNotSentTo($patientUser, ChargeDueSoonNotification::class);
    }

    public function test_sends_overdue_reminder_once_for_a_vencida_charge(): void
    {
        Notification::fake();

        [$patient, $patientUser] = $this->makePatient();

        $charge = FinancialCharge::query()->create([
            'tenant_id' => $patient->tenant_id,
            'patient_id' => $patient->id,
            'amount' => 100,
            'due_date' => now()->subDays(5),
            'status' => ChargeStatus::Vencido,
        ]);

        $this->artisan('notifications:send-charge-reminders')->assertSuccessful();
        Notification::assertSentTo($patientUser, ChargeOverdueNotification::class);

        $charge->refresh();
        $this->assertNotNull($charge->overdue_reminder_sent_at);

        Notification::fake();
        $this->artisan('notifications:send-charge-reminders')->assertSuccessful();
        Notification::assertNotSentTo($patientUser, ChargeOverdueNotification::class);
    }

    public function test_does_not_send_due_soon_reminder_for_a_paid_charge(): void
    {
        Notification::fake();

        [$patient, $patientUser] = $this->makePatient();

        FinancialCharge::query()->create([
            'tenant_id' => $patient->tenant_id,
            'patient_id' => $patient->id,
            'amount' => 100,
            'due_date' => now()->addDay(),
            'status' => ChargeStatus::Pago,
        ]);

        $this->artisan('notifications:send-charge-reminders')->assertSuccessful();

        Notification::assertNotSentTo($patientUser, ChargeDueSoonNotification::class);
    }
}

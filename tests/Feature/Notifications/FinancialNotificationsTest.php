<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Modules\Financial\Enums\ChargeStatus;
use Modules\Financial\Models\FinancialCharge;
use Modules\Notifications\Notifications\ChargeCreatedNotification;
use Modules\Notifications\Notifications\PaymentConfirmedNotification;
use Modules\Notifications\Notifications\PaymentReversedNotification;
use Modules\Patients\Models\Patient;
use Modules\Payments\Models\Payment;
use Modules\Tenant\Models\Tenant;
use Modules\Users\Models\User;
use Tests\TestCase;

class FinancialNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdminAndPatient(): array
    {
        $tenant = Tenant::factory()->create();

        $admin = User::factory()->create(['tenant_id' => $tenant->id]);
        $admin->assignRole('admin_clinica');

        $patientUser = User::factory()->create(['tenant_id' => $tenant->id]);
        $patient = Patient::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $patientUser->id,
            'display_name' => $patientUser->name,
            'email' => $patientUser->email,
        ]);

        return [$admin, $patient, $patientUser];
    }

    public function test_creating_a_charge_notifies_the_patient(): void
    {
        Notification::fake();

        [$admin, $patient, $patientUser] = $this->makeAdminAndPatient();

        $this->actingAs($admin)->post("/pacientes/{$patient->id}/financeiro/cobrancas", [
            'amount' => '150.00',
            'due_date' => now()->addDays(10)->toDateString(),
        ])->assertRedirect();

        Notification::assertSentTo($patientUser, ChargeCreatedNotification::class);
    }

    public function test_installments_notify_the_patient_once_per_installment(): void
    {
        Notification::fake();

        [$admin, $patient, $patientUser] = $this->makeAdminAndPatient();

        $this->actingAs($admin)->post("/pacientes/{$patient->id}/financeiro/cobrancas", [
            'amount' => '150.00',
            'due_date' => now()->addDays(10)->toDateString(),
            'installment_total' => 3,
        ]);

        Notification::assertSentToTimes($patientUser, ChargeCreatedNotification::class, 3);
    }

    public function test_recording_a_payment_notifies_the_patient(): void
    {
        Notification::fake();

        [$admin, $patient, $patientUser] = $this->makeAdminAndPatient();

        $charge = FinancialCharge::query()->create([
            'tenant_id' => $patient->tenant_id,
            'patient_id' => $patient->id,
            'amount' => 100,
            'due_date' => now()->addDays(5),
            'status' => ChargeStatus::EmAberto,
        ]);

        $this->actingAs($admin)->post("/financeiro/cobrancas/{$charge->id}/pagamentos", [
            'amount' => '100.00',
            'method' => 'dinheiro',
        ])->assertRedirect();

        Notification::assertSentTo($patientUser, PaymentConfirmedNotification::class);
    }

    public function test_reversing_a_payment_notifies_the_patient(): void
    {
        Notification::fake();

        [$admin, $patient, $patientUser] = $this->makeAdminAndPatient();

        $charge = FinancialCharge::query()->create([
            'tenant_id' => $patient->tenant_id,
            'patient_id' => $patient->id,
            'amount' => 100,
            'due_date' => now()->addDays(5),
            'status' => ChargeStatus::EmAberto,
        ]);

        $this->actingAs($admin)->post("/financeiro/cobrancas/{$charge->id}/pagamentos", [
            'amount' => '100.00',
            'method' => 'cartao',
        ]);

        $payment = Payment::query()->where('charge_id', $charge->id)->sole();

        $this->actingAs($admin)->post("/financeiro/pagamentos/{$payment->id}/estornar")->assertRedirect();

        Notification::assertSentTo($patientUser, PaymentReversedNotification::class);
    }
}

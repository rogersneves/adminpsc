<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Financial\Enums\ChargeStatus;
use Modules\Financial\Models\FinancialCharge;
use Modules\Patients\Models\Patient;
use Modules\Payments\Enums\PaymentMethod;
use Modules\Payments\Models\Payment;
use Modules\Psychologists\Models\Psychologist;
use Modules\Scheduling\Enums\SessionModality;
use Modules\Scheduling\Enums\SessionStatus;
use Modules\Scheduling\Models\Session;
use Modules\Tenant\Models\Tenant;
use Modules\Users\Models\User;
use Tests\TestCase;

class PaymentReceiptTest extends TestCase
{
    use RefreshDatabase;

    private function makeScenario(): array
    {
        $tenant = Tenant::factory()->create();

        $patientUser = User::factory()->create(['tenant_id' => $tenant->id]);
        $patientUser->assignRole('paciente');
        $patient = Patient::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $patientUser->id,
            'display_name' => $patientUser->name,
            'email' => $patientUser->email,
        ]);

        $psychologistUser = User::factory()->create(['tenant_id' => $tenant->id]);
        $psychologistUser->assignRole('psicologo');
        $psychologist = Psychologist::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $psychologistUser->id,
            'professional_registry_encrypted' => 'CRP 00/00000',
            'specialties' => [],
            'default_session_duration_minutes' => 50,
        ]);

        $session = Session::query()->create([
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
            'psychologist_id' => $psychologist->id,
            'scheduled_at' => now()->subDay(),
            'duration_minutes' => 50,
            'modality' => SessionModality::Online,
            'status' => SessionStatus::Realizada,
        ]);

        $charge = FinancialCharge::query()->create([
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
            'session_id' => $session->id,
            'amount' => 100,
            'due_date' => now()->subDays(2),
            'status' => ChargeStatus::Pago,
        ]);

        $payment = Payment::query()->create([
            'tenant_id' => $tenant->id,
            'charge_id' => $charge->id,
            'amount' => 100,
            'paid_at' => now(),
            'method' => PaymentMethod::Pix,
        ]);

        return compact('tenant', 'patientUser', 'patient', 'psychologistUser', 'psychologist', 'session', 'charge', 'payment');
    }

    public function test_patient_can_download_their_own_receipt(): void
    {
        ['patientUser' => $patientUser, 'payment' => $payment] = $this->makeScenario();

        $response = $this->actingAs($patientUser)->get("/financeiro/pagamentos/{$payment->id}/recibo");

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_treating_psychologist_can_download_the_receipt(): void
    {
        ['psychologistUser' => $psychologistUser, 'payment' => $payment] = $this->makeScenario();

        $this->actingAs($psychologistUser)->get("/financeiro/pagamentos/{$payment->id}/recibo")->assertOk();
    }

    public function test_unrelated_psychologist_cannot_download_the_receipt(): void
    {
        ['tenant' => $tenant, 'payment' => $payment] = $this->makeScenario();

        $otherPsychologistUser = User::factory()->create(['tenant_id' => $tenant->id]);
        $otherPsychologistUser->assignRole('psicologo');
        Psychologist::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $otherPsychologistUser->id,
            'professional_registry_encrypted' => 'CRP 00/11111',
            'specialties' => [],
            'default_session_duration_minutes' => 50,
        ]);

        $this->actingAs($otherPsychologistUser)->get("/financeiro/pagamentos/{$payment->id}/recibo")->assertForbidden();
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Financial\Enums\ChargeStatus;
use Modules\Financial\Models\FinancialCharge;
use Modules\Patients\Models\Patient;
use Modules\Payments\Models\Payment;
use Modules\Tenant\Models\Tenant;
use Modules\Users\Models\User;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdminAndCharge(): array
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

        $charge = FinancialCharge::query()->create([
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
            'amount' => 100,
            'due_date' => now()->addDays(5),
            'status' => ChargeStatus::EmAberto,
        ]);

        return [$admin, $charge];
    }

    public function test_partial_payment_marks_charge_as_parcial(): void
    {
        [$admin, $charge] = $this->makeAdminAndCharge();

        $response = $this->actingAs($admin)->post("/financeiro/cobrancas/{$charge->id}/pagamentos", [
            'amount' => '40.00',
            'method' => 'dinheiro',
        ]);

        $response->assertRedirect();
        $this->assertSame(ChargeStatus::Parcial, $charge->fresh()->status);
    }

    public function test_full_payment_marks_charge_as_pago(): void
    {
        [$admin, $charge] = $this->makeAdminAndCharge();

        $this->actingAs($admin)->post("/financeiro/cobrancas/{$charge->id}/pagamentos", [
            'amount' => '100.00',
            'method' => 'pix',
        ]);

        $this->assertSame(ChargeStatus::Pago, $charge->fresh()->status);
    }

    public function test_reversing_the_only_payment_marks_charge_as_estornado(): void
    {
        [$admin, $charge] = $this->makeAdminAndCharge();

        $this->actingAs($admin)->post("/financeiro/cobrancas/{$charge->id}/pagamentos", [
            'amount' => '100.00',
            'method' => 'cartao',
        ]);

        $payment = Payment::query()->where('charge_id', $charge->id)->sole();

        $response = $this->actingAs($admin)->post("/financeiro/pagamentos/{$payment->id}/estornar");

        $response->assertRedirect();
        $this->assertNotNull($payment->fresh()->reversed_at);
        $this->assertSame(ChargeStatus::Estornado, $charge->fresh()->status);
    }
}

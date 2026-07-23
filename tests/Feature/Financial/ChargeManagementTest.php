<?php

declare(strict_types=1);

namespace Tests\Feature\Financial;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Financial\Enums\ChargeStatus;
use Modules\Financial\Models\FinancialCharge;
use Modules\Patients\Models\Patient;
use Modules\Tenant\Models\Tenant;
use Modules\Users\Models\User;
use Tests\TestCase;

class ChargeManagementTest extends TestCase
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

    public function test_discount_can_be_updated_before_any_payment(): void
    {
        [$admin, $charge] = $this->makeAdminAndCharge();

        $response = $this->actingAs($admin)->put("/financeiro/cobrancas/{$charge->id}", [
            'discount_amount' => '20.00',
        ]);

        $response->assertRedirect();
        $this->assertSame('20.00', (string) $charge->fresh()->discount_amount);
    }

    public function test_discount_cannot_be_updated_after_a_payment_exists(): void
    {
        [$admin, $charge] = $this->makeAdminAndCharge();

        $this->actingAs($admin)->post("/financeiro/cobrancas/{$charge->id}/pagamentos", [
            'amount' => '50.00',
            'method' => 'dinheiro',
        ]);

        $response = $this->actingAs($admin)->put("/financeiro/cobrancas/{$charge->id}", [
            'discount_amount' => '20.00',
        ]);

        $response->assertSessionHasErrors('discount_amount');
        $this->assertSame('0.00', (string) $charge->fresh()->discount_amount);
    }

    public function test_charge_without_payment_can_be_cancelled(): void
    {
        [$admin, $charge] = $this->makeAdminAndCharge();

        $response = $this->actingAs($admin)->delete("/financeiro/cobrancas/{$charge->id}");

        $response->assertRedirect();
        $this->assertSame(ChargeStatus::Cancelado, $charge->fresh()->status);
    }

    public function test_charge_with_a_payment_cannot_be_cancelled(): void
    {
        [$admin, $charge] = $this->makeAdminAndCharge();

        $this->actingAs($admin)->post("/financeiro/cobrancas/{$charge->id}/pagamentos", [
            'amount' => '50.00',
            'method' => 'dinheiro',
        ]);

        $response = $this->actingAs($admin)->delete("/financeiro/cobrancas/{$charge->id}");

        $response->assertSessionHasErrors('charge');
        $this->assertSame(ChargeStatus::Parcial, $charge->fresh()->status);
    }
}

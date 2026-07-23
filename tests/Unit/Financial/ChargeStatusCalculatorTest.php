<?php

declare(strict_types=1);

namespace Tests\Unit\Financial;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Financial\Enums\ChargeStatus;
use Modules\Financial\Models\FinancialCharge;
use Modules\Financial\Services\ChargeStatusCalculator;
use Modules\Patients\Models\Patient;
use Modules\Payments\Enums\PaymentMethod;
use Modules\Payments\Models\Payment;
use Modules\Tenant\Models\Tenant;
use Modules\Users\Models\User;
use Tests\TestCase;

class ChargeStatusCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private function makeCharge(array $attributes = []): FinancialCharge
    {
        $tenant = Tenant::factory()->create();

        $patientUser = User::factory()->create(['tenant_id' => $tenant->id]);
        $patient = Patient::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $patientUser->id,
            'display_name' => $patientUser->name,
            'email' => $patientUser->email,
        ]);

        return FinancialCharge::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
            'amount' => 100,
            'discount_amount' => 0,
            'fine_amount' => 0,
            'interest_amount' => 0,
            'due_date' => now()->addDays(5),
            'status' => ChargeStatus::EmAberto,
            'installment_number' => 1,
            'installment_total' => 1,
        ], $attributes));
    }

    private function addPayment(FinancialCharge $charge, float $amount, bool $reversed = false): Payment
    {
        return Payment::query()->create([
            'tenant_id' => $charge->tenant_id,
            'charge_id' => $charge->id,
            'amount' => $amount,
            'paid_at' => now(),
            'method' => PaymentMethod::Dinheiro,
            'reversed_at' => $reversed ? now() : null,
        ]);
    }

    public function test_charge_with_no_payment_and_future_due_date_is_em_aberto(): void
    {
        $charge = $this->makeCharge();

        $this->assertSame(ChargeStatus::EmAberto, (new ChargeStatusCalculator)->recalculate($charge));
    }

    public function test_charge_with_no_payment_and_past_due_date_is_vencido(): void
    {
        $charge = $this->makeCharge(['due_date' => now()->subDay()]);

        $this->assertSame(ChargeStatus::Vencido, (new ChargeStatusCalculator)->recalculate($charge));
    }

    public function test_partial_payment_is_parcial(): void
    {
        $charge = $this->makeCharge();
        $this->addPayment($charge, 40);

        $this->assertSame(ChargeStatus::Parcial, (new ChargeStatusCalculator)->recalculate($charge));
    }

    public function test_full_payment_is_pago(): void
    {
        $charge = $this->makeCharge();
        $this->addPayment($charge, 100);

        $this->assertSame(ChargeStatus::Pago, (new ChargeStatusCalculator)->recalculate($charge));
    }

    public function test_fully_reversed_payment_is_estornado_not_em_aberto(): void
    {
        $charge = $this->makeCharge();
        $this->addPayment($charge, 100, reversed: true);

        $this->assertSame(ChargeStatus::Estornado, (new ChargeStatusCalculator)->recalculate($charge));
    }

    public function test_partially_reversed_leaves_parcial_from_remaining_payment(): void
    {
        $charge = $this->makeCharge();
        $this->addPayment($charge, 60);
        $this->addPayment($charge, 40, reversed: true);

        $this->assertSame(ChargeStatus::Parcial, (new ChargeStatusCalculator)->recalculate($charge));
    }

    public function test_cancelled_charge_is_never_recalculated(): void
    {
        $charge = $this->makeCharge(['status' => ChargeStatus::Cancelado]);
        $this->addPayment($charge, 100);

        $this->assertSame(ChargeStatus::Cancelado, (new ChargeStatusCalculator)->recalculate($charge));
    }

    public function test_discount_reduces_total_due(): void
    {
        $charge = $this->makeCharge(['discount_amount' => 30]);
        $this->addPayment($charge, 70);

        $this->assertSame(ChargeStatus::Pago, (new ChargeStatusCalculator)->recalculate($charge));
    }
}

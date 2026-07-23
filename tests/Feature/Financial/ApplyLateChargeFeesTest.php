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

class ApplyLateChargeFeesTest extends TestCase
{
    use RefreshDatabase;

    private function makePatient(): Patient
    {
        $tenant = Tenant::factory()->create();
        $patientUser = User::factory()->create(['tenant_id' => $tenant->id]);

        return Patient::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $patientUser->id,
            'display_name' => $patientUser->name,
            'email' => $patientUser->email,
        ]);
    }

    public function test_overdue_open_charge_receives_fine_and_interest_and_becomes_vencido(): void
    {
        $patient = $this->makePatient();

        $charge = FinancialCharge::query()->create([
            'tenant_id' => $patient->tenant_id,
            'patient_id' => $patient->id,
            'amount' => 100,
            'due_date' => today()->subDays(10),
            'status' => ChargeStatus::EmAberto,
        ]);

        $this->artisan('financial:apply-late-fees')->assertSuccessful();

        $charge->refresh();
        $this->assertSame(ChargeStatus::Vencido, $charge->status);
        // 2% multa (default) sobre 100 = 2.00
        $this->assertSame('2.00', (string) $charge->fine_amount);
        // 1% ao mês (default) pro-rata die, 10 dias: 100 * (1/100/30) * 10 = 0.33
        $this->assertSame('0.33', (string) $charge->interest_amount);
    }

    public function test_charge_not_yet_due_is_untouched(): void
    {
        $patient = $this->makePatient();

        $charge = FinancialCharge::query()->create([
            'tenant_id' => $patient->tenant_id,
            'patient_id' => $patient->id,
            'amount' => 100,
            'due_date' => today()->addDays(5),
            'status' => ChargeStatus::EmAberto,
        ]);

        $this->artisan('financial:apply-late-fees')->assertSuccessful();

        $charge->refresh();
        $this->assertSame(ChargeStatus::EmAberto, $charge->status);
        $this->assertSame('0.00', (string) $charge->fine_amount);
    }

    public function test_charge_already_paid_is_untouched_even_if_overdue(): void
    {
        $patient = $this->makePatient();

        $charge = FinancialCharge::query()->create([
            'tenant_id' => $patient->tenant_id,
            'patient_id' => $patient->id,
            'amount' => 100,
            'due_date' => today()->subDays(10),
            'status' => ChargeStatus::Pago,
        ]);

        $this->artisan('financial:apply-late-fees')->assertSuccessful();

        $charge->refresh();
        $this->assertSame(ChargeStatus::Pago, $charge->status);
        $this->assertSame('0.00', (string) $charge->fine_amount);
    }
}

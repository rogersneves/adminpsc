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

class CreateChargeTest extends TestCase
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

        return [$admin, $patient];
    }

    public function test_creates_a_single_charge(): void
    {
        [$admin, $patient] = $this->makeAdminAndPatient();

        $response = $this->actingAs($admin)->post("/pacientes/{$patient->id}/financeiro/cobrancas", [
            'amount' => '150.00',
            'due_date' => now()->addDays(10)->toDateString(),
        ]);

        $response->assertRedirect();

        $charge = FinancialCharge::query()->where('patient_id', $patient->id)->sole();
        $this->assertSame('150.00', (string) $charge->amount);
        $this->assertSame(ChargeStatus::EmAberto, $charge->status);
        $this->assertSame(1, $charge->installment_number);
        $this->assertSame(1, $charge->installment_total);
    }

    public function test_installments_split_the_amount_and_space_due_dates_by_month(): void
    {
        [$admin, $patient] = $this->makeAdminAndPatient();

        $firstDueDate = now()->addDays(10)->startOfDay();

        $this->actingAs($admin)->post("/pacientes/{$patient->id}/financeiro/cobrancas", [
            'amount' => '100.00',
            'due_date' => $firstDueDate->toDateString(),
            'installment_total' => 3,
        ]);

        $charges = FinancialCharge::query()
            ->where('patient_id', $patient->id)
            ->orderBy('installment_number')
            ->get();

        $this->assertCount(3, $charges);
        $this->assertSame([1, 2, 3], $charges->pluck('installment_number')->all());
        $this->assertTrue($charges->every(fn (FinancialCharge $c) => $c->installment_total === 3));

        // a última parcela absorve o resto da divisão em centavos
        $this->assertSame('33.33', (string) $charges[0]->amount);
        $this->assertSame('33.33', (string) $charges[1]->amount);
        $this->assertSame('33.34', (string) $charges[2]->amount);

        $this->assertSame(
            $firstDueDate->toDateString(),
            $charges[0]->due_date->toDateString(),
        );
        $this->assertSame(
            $firstDueDate->copy()->addMonthsNoOverflow(1)->toDateString(),
            $charges[1]->due_date->toDateString(),
        );
        $this->assertSame(
            $firstDueDate->copy()->addMonthsNoOverflow(2)->toDateString(),
            $charges[2]->due_date->toDateString(),
        );
    }
}

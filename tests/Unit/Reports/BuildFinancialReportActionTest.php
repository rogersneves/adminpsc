<?php

declare(strict_types=1);

namespace Tests\Unit\Reports;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Financial\Enums\ChargeStatus;
use Modules\Financial\Models\FinancialCharge;
use Modules\Patients\Models\Patient;
use Modules\Reports\Actions\BuildFinancialReportAction;
use Modules\Tenant\Models\Tenant;
use Modules\Users\Models\User;
use Tests\TestCase;

class BuildFinancialReportActionTest extends TestCase
{
    use RefreshDatabase;

    private function makePatient(Tenant $tenant): Patient
    {
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        return Patient::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'display_name' => $user->name,
            'email' => $user->email,
        ]);
    }

    private function makeCharge(Tenant $tenant, Patient $patient, string $dueDate, ChargeStatus $status = ChargeStatus::EmAberto): FinancialCharge
    {
        return FinancialCharge::query()->create([
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
            'amount' => 100,
            'due_date' => $dueDate,
            'status' => $status,
        ]);
    }

    public function test_filters_by_scope_status_and_period(): void
    {
        $tenant = Tenant::factory()->create();
        $patientA = $this->makePatient($tenant);
        $patientB = $this->makePatient($tenant);

        $this->makeCharge($tenant, $patientA, now()->addDays(5)->toDateString(), ChargeStatus::Vencido);
        $this->makeCharge($tenant, $patientA, now()->addDays(5)->toDateString(), ChargeStatus::Pago);
        $this->makeCharge($tenant, $patientB, now()->addDays(5)->toDateString(), ChargeStatus::Vencido);

        $rows = (new BuildFinancialReportAction)([$patientA->id], null, ChargeStatus::Vencido);

        $this->assertCount(1, $rows);
        $this->assertSame($patientA->display_name, $rows->first()['patient_name']);
        $this->assertSame('vencido', $rows->first()['status']);
    }

    public function test_totals_reflect_discount_and_payments(): void
    {
        $tenant = Tenant::factory()->create();
        $patient = $this->makePatient($tenant);

        FinancialCharge::query()->create([
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
            'amount' => 100,
            'discount_amount' => 10,
            'due_date' => now()->addDays(5)->toDateString(),
            'status' => ChargeStatus::EmAberto,
        ]);

        $rows = (new BuildFinancialReportAction)(null);

        $this->assertSame(90.0, $rows->first()['total_due']);
        $this->assertSame(0.0, $rows->first()['total_paid']);
    }
}

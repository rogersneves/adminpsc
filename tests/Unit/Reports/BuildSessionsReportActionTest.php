<?php

declare(strict_types=1);

namespace Tests\Unit\Reports;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Patients\Models\Patient;
use Modules\Psychologists\Models\Psychologist;
use Modules\Reports\Actions\BuildSessionsReportAction;
use Modules\Scheduling\Enums\SessionModality;
use Modules\Scheduling\Enums\SessionStatus;
use Modules\Scheduling\Models\Session;
use Modules\Tenant\Models\Tenant;
use Modules\Users\Models\User;
use Tests\TestCase;

class BuildSessionsReportActionTest extends TestCase
{
    use RefreshDatabase;

    private function makePsychologist(Tenant $tenant): Psychologist
    {
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        return Psychologist::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'professional_registry_encrypted' => 'CRP 00/00000',
            'specialties' => [],
            'default_session_duration_minutes' => 50,
        ]);
    }

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

    private function makeSession(Tenant $tenant, Psychologist $psychologist, Patient $patient, string $scheduledAt, SessionStatus $status = SessionStatus::Agendada): Session
    {
        return Session::query()->create([
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
            'psychologist_id' => $psychologist->id,
            'scheduled_at' => $scheduledAt,
            'duration_minutes' => 50,
            'modality' => SessionModality::Online,
            'status' => $status,
        ]);
    }

    public function test_filters_by_patient_ids_scope(): void
    {
        $tenant = Tenant::factory()->create();
        $psychologist = $this->makePsychologist($tenant);
        $patientA = $this->makePatient($tenant);
        $patientB = $this->makePatient($tenant);

        $this->makeSession($tenant, $psychologist, $patientA, now()->addDay()->toDateTimeString());
        $this->makeSession($tenant, $psychologist, $patientB, now()->addDays(2)->toDateTimeString());

        $rows = (new BuildSessionsReportAction)([$patientA->id]);

        $this->assertCount(1, $rows);
        $this->assertSame($patientA->display_name, $rows->first()['patient_name']);
    }

    public function test_null_scope_returns_everything_in_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $psychologist = $this->makePsychologist($tenant);
        $patientA = $this->makePatient($tenant);
        $patientB = $this->makePatient($tenant);

        $this->makeSession($tenant, $psychologist, $patientA, now()->addDay()->toDateTimeString());
        $this->makeSession($tenant, $psychologist, $patientB, now()->addDays(2)->toDateTimeString());

        $rows = (new BuildSessionsReportAction)(null);

        $this->assertCount(2, $rows);
    }

    public function test_filters_by_period(): void
    {
        $tenant = Tenant::factory()->create();
        $psychologist = $this->makePsychologist($tenant);
        $patient = $this->makePatient($tenant);

        $inRange = $this->makeSession($tenant, $psychologist, $patient, now()->addDays(5)->toDateTimeString());
        $this->makeSession($tenant, $psychologist, $patient, now()->addDays(20)->toDateTimeString());

        $rows = (new BuildSessionsReportAction)(
            null,
            null,
            now()->addDays(1)->startOfDay()->toImmutable(),
            now()->addDays(10)->endOfDay()->toImmutable(),
        );

        $this->assertCount(1, $rows);
        $this->assertSame($patient->display_name, $rows->first()['patient_name']);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Reports;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Patients\Models\Patient;
use Modules\Psychologists\Models\Psychologist;
use Modules\Reports\Actions\BuildAttendanceReportAction;
use Modules\Scheduling\Enums\SessionModality;
use Modules\Scheduling\Enums\SessionStatus;
use Modules\Scheduling\Models\Session;
use Modules\Tenant\Models\Tenant;
use Modules\Users\Models\User;
use Tests\TestCase;

class BuildAttendanceReportActionTest extends TestCase
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

    private function makeSession(Tenant $tenant, Psychologist $psychologist, Patient $patient, SessionStatus $status): Session
    {
        return Session::query()->create([
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
            'psychologist_id' => $psychologist->id,
            'scheduled_at' => now()->subDay(),
            'duration_minutes' => 50,
            'modality' => SessionModality::Online,
            'status' => $status,
        ]);
    }

    public function test_attendance_rate_counts_only_realizada_and_nao_compareceu(): void
    {
        $tenant = Tenant::factory()->create();
        $psychologist = $this->makePsychologist($tenant);
        $patient = $this->makePatient($tenant);

        $this->makeSession($tenant, $psychologist, $patient, SessionStatus::Realizada);
        $this->makeSession($tenant, $psychologist, $patient, SessionStatus::Realizada);
        $this->makeSession($tenant, $psychologist, $patient, SessionStatus::Realizada);
        $this->makeSession($tenant, $psychologist, $patient, SessionStatus::NaoCompareceu);
        $this->makeSession($tenant, $psychologist, $patient, SessionStatus::Cancelada);
        $this->makeSession($tenant, $psychologist, $patient, SessionStatus::Reagendada);

        $rows = (new BuildAttendanceReportAction)(null);

        $this->assertCount(1, $rows);
        $row = $rows->first();
        $this->assertSame(3, $row['realizada']);
        $this->assertSame(1, $row['nao_compareceu']);
        $this->assertSame(0.75, $row['attendance_rate']);
    }

    public function test_scope_restricts_to_given_patient_ids(): void
    {
        $tenant = Tenant::factory()->create();
        $psychologist = $this->makePsychologist($tenant);
        $patientA = $this->makePatient($tenant);
        $patientB = $this->makePatient($tenant);

        $this->makeSession($tenant, $psychologist, $patientA, SessionStatus::Realizada);
        $this->makeSession($tenant, $psychologist, $patientB, SessionStatus::NaoCompareceu);

        $rows = (new BuildAttendanceReportAction)([$patientA->id]);

        $this->assertCount(1, $rows);
        $this->assertSame($patientA->display_name, $rows->first()['patient_name']);
    }
}

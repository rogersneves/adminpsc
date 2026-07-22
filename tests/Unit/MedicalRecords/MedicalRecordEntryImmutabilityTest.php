<?php

declare(strict_types=1);

namespace Tests\Unit\MedicalRecords;

use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Modules\MedicalRecords\Models\MedicalRecordEntry;
use Modules\Patients\Models\Patient;
use Modules\Psychologists\Models\Psychologist;
use Modules\Tenant\Models\Tenant;
use Modules\Users\Models\User;
use Tests\TestCase;

class MedicalRecordEntryImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    private function makeEntry(): MedicalRecordEntry
    {
        $tenant = Tenant::factory()->create();

        $psychologistUser = User::factory()->create(['tenant_id' => $tenant->id]);
        $psychologist = Psychologist::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $psychologistUser->id,
            'professional_registry_encrypted' => 'CRP 00/00000',
            'specialties' => [],
            'default_session_duration_minutes' => 50,
        ]);

        $patientUser = User::factory()->create(['tenant_id' => $tenant->id]);
        $patient = Patient::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $patientUser->id,
            'display_name' => $patientUser->name,
            'email' => $patientUser->email,
        ]);

        return MedicalRecordEntry::query()->create([
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
            'psychologist_id' => $psychologist->id,
            'version' => 1,
            'content_encrypted' => ['notes' => 'Original'],
            'created_at' => now(),
        ]);
    }

    public function test_update_throws(): void
    {
        $entry = $this->makeEntry();

        $this->expectException(LogicException::class);

        $entry->update(['content_encrypted' => ['notes' => 'Adulterado']]);
    }

    public function test_soft_delete_is_still_allowed_for_exceptional_administrative_removal(): void
    {
        $entry = $this->makeEntry();

        $entry->delete();

        $this->assertSoftDeleted($entry);
    }
}

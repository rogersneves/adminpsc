<?php

declare(strict_types=1);

namespace Tests\Feature\Patients;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Patients\Models\Patient;
use Modules\Security\Services\EncryptionService;
use Modules\Tenant\Models\Tenant;
use Modules\Users\Models\User;
use Tests\TestCase;

class PatientProfileTest extends TestCase
{
    use RefreshDatabase;

    private function patientUser(): User
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'email_verified_at' => now()]);
        $user->assignRole('paciente');

        Patient::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'display_name' => $user->name,
            'email' => $user->email,
        ]);

        return $user;
    }

    public function test_updating_profile_encrypts_sensitive_fields_at_rest(): void
    {
        $user = $this->patientUser();

        $response = $this
            ->actingAs($user)
            ->put('/paciente/perfil', [
                'document_number' => '123.456.789-00',
                'birth_date' => '1990-05-20',
                'address' => 'Rua das Flores, 123',
                'phones' => ['(11) 91234-5678'],
            ]);

        $response->assertRedirect();

        $row = DB::table('patients')->where('user_id', $user->id)->first();

        $this->assertStringNotContainsString('123456789', $row->document_number_encrypted);
        $this->assertStringNotContainsString('Flores', $row->address_encrypted);
        $this->assertStringNotContainsString('91234', $row->phones_encrypted);

        $patient = Patient::query()->where('user_id', $user->id)->first();
        $this->assertSame('Rua das Flores, 123', $patient->address_encrypted);
        $this->assertSame(['(11) 91234-5678'], $patient->phones_encrypted);
    }

    public function test_patient_can_be_found_by_document_number_search_hash(): void
    {
        $user = $this->patientUser();

        $this->actingAs($user)->put('/paciente/perfil', [
            'document_number' => '123.456.789-00',
        ]);

        $expectedHash = app(EncryptionService::class)->searchHash('12345678900', 'patient_document_number');

        $patient = Patient::query()->where('document_number_hash', $expectedHash)->first();

        $this->assertNotNull($patient);
        $this->assertSame($user->id, $patient->user_id);
    }

    public function test_birth_date_implying_minor_without_guardian_fails_validation(): void
    {
        $user = $this->patientUser();
        $recentBirthDate = now()->subYears(10)->toDateString();

        $response = $this->actingAs($user)->put('/paciente/perfil', [
            'birth_date' => $recentBirthDate,
        ]);

        $response->assertSessionHasErrors('birth_date');
    }

    public function test_birth_date_implying_minor_with_guardian_in_same_request_succeeds(): void
    {
        $user = $this->patientUser();
        $recentBirthDate = now()->subYears(10)->toDateString();

        $response = $this->actingAs($user)->put('/paciente/perfil', [
            'birth_date' => $recentBirthDate,
            'guardians' => [
                ['name' => 'Mãe Responsável', 'relationship' => 'mae', 'email' => 'mae@example.com'],
            ],
        ]);

        $response->assertSessionDoesntHaveErrors();

        $patient = Patient::query()->where('user_id', $user->id)->first();
        $this->assertCount(1, $patient->guardians);
    }
}

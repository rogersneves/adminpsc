<?php

declare(strict_types=1);

namespace Tests\Feature\Lgpd;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Guardians\Models\Guardian;
use Modules\Patients\Models\Patient;
use Modules\Security\Actions\Lgpd\AnonymizePatientAction;
use Modules\Tenant\Models\Tenant;
use Modules\Users\Models\User;
use Tests\TestCase;

class AnonymizePatientTest extends TestCase
{
    use RefreshDatabase;

    private function makePatient(): array
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'name' => 'João', 'email' => 'joao@ex.com']);
        $patient = Patient::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'display_name' => 'João Silva',
            'email' => 'joao@ex.com',
            'document_number_encrypted' => '12345678900',
            'document_number_hash' => hash('sha256', '12345678900'),
            'phones_encrypted' => ['11999998888'],
            'address_encrypted' => 'Rua A, 1',
        ]);
        Guardian::query()->create([
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
            'name' => 'Responsável',
            'document_number_encrypted' => '99999999999',
            'phone_encrypted' => '1130001111',
            'relationship' => 'mãe',
        ]);

        return [$patient, $user];
    }

    public function test_anonymization_clears_pii_soft_deletes_and_audits(): void
    {
        [$patient, $user] = $this->makePatient();

        app(AnonymizePatientAction::class)($patient);

        $fresh = Patient::query()->withoutTenantScope()->withTrashed()->find($patient->id);
        $this->assertNotNull($fresh->anonymized_at);
        $this->assertNotNull($fresh->deleted_at);
        $this->assertSame('[titular anonimizado]', $fresh->display_name);
        $this->assertNull($fresh->document_number_encrypted);
        $this->assertNull($fresh->document_number_hash);
        $this->assertNull($fresh->phones_encrypted);
        $this->assertNull($fresh->address_encrypted);

        // Responsável também limpo.
        $guardian = Guardian::query()->withoutTenantScope()->withTrashed()->where('patient_id', $patient->id)->first();
        $this->assertSame('[titular anonimizado]', $guardian->name);
        $this->assertNull($guardian->document_number_encrypted);

        // Conta de login limpa.
        $this->assertSame('[titular anonimizado]', $user->fresh()->name);
        $this->assertStringContainsString('anonimizado.invalid', $user->fresh()->email);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'lgpd.patient_anonymized',
            'auditable_id' => $patient->id,
        ]);
    }

    public function test_anonymization_is_idempotent(): void
    {
        [$patient] = $this->makePatient();

        $action = app(AnonymizePatientAction::class);
        $action($patient);
        $firstAt = Patient::withoutTenantScope()->withTrashed()->find($patient->id)->anonymized_at;

        $action($patient->fresh());
        $secondAt = Patient::withoutTenantScope()->withTrashed()->find($patient->id)->anonymized_at;

        $this->assertEquals($firstAt, $secondAt);
    }

    public function test_command_anonymizes_with_force(): void
    {
        [$patient] = $this->makePatient();

        $this->artisan('lgpd:anonymize-patient', ['patient' => $patient->id, '--force' => true])
            ->assertSuccessful();

        $this->assertNotNull(Patient::withoutTenantScope()->withTrashed()->find($patient->id)->anonymized_at);
    }

    public function test_command_refuses_without_force(): void
    {
        [$patient] = $this->makePatient();

        $this->artisan('lgpd:anonymize-patient', ['patient' => $patient->id])
            ->assertFailed();

        $this->assertNull(Patient::withoutTenantScope()->withTrashed()->find($patient->id)->anonymized_at);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Patients\Models\Patient;
use Modules\Security\Jobs\RotateEncryptionKeyJob;
use Modules\Security\Services\EncryptionService;
use Modules\Tenant\Models\Tenant;
use Tests\TestCase;

/**
 * A recifragem em massa após rotação, usando um Model real (Patient) e o cast
 * EncryptedJson (patient_phones). Config aponta o contexto para o Model; o Job
 * descobre o atributo pelo getCasts().
 */
class RotateEncryptionKeyJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_reencrypts_existing_rows_to_the_new_key_version(): void
    {
        $tenant = Tenant::factory()->create();

        $patient = Patient::query()->create([
            'tenant_id' => $tenant->id,
            'display_name' => 'Fulano',
            'email' => 'fulano@example.com',
            'phones_encrypted' => ['11999998888'],
        ]);

        // Cifrado com a v1.
        $this->assertStringEndsWith('.1', DB::table('patients')->where('id', $patient->id)->value('phones_encrypted'));

        app(EncryptionService::class)->rotate('patient_phones');
        (new RotateEncryptionKeyJob('patient_phones'))->handle();

        // Agora cifrado com a v2, mas o valor decifrado é idêntico.
        $rawAfter = DB::table('patients')->where('id', $patient->id)->value('phones_encrypted');
        $this->assertStringEndsWith('.2', $rawAfter);
        $this->assertSame(['11999998888'], $patient->fresh()->phones_encrypted);
    }

    public function test_job_only_touches_attributes_of_the_rotated_context(): void
    {
        $tenant = Tenant::factory()->create();

        $patient = Patient::query()->create([
            'tenant_id' => $tenant->id,
            'display_name' => 'Ciclano',
            'email' => 'ciclano@example.com',
            'phones_encrypted' => ['11888'],
            'address_encrypted' => 'Rua X, 100',
        ]);

        app(EncryptionService::class)->rotate('patient_phones');
        (new RotateEncryptionKeyJob('patient_phones'))->handle();

        // phones migrou para v2; address (outro contexto, não rotacionado) segue v1.
        $this->assertStringEndsWith('.2', DB::table('patients')->where('id', $patient->id)->value('phones_encrypted'));
        $this->assertStringEndsWith('.1', DB::table('patients')->where('id', $patient->id)->value('address_encrypted'));
    }
}

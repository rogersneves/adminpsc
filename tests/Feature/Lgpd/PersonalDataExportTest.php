<?php

declare(strict_types=1);

namespace Tests\Feature\Lgpd;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Patients\Models\Patient;
use Modules\Tenant\Models\Tenant;
use Modules\Users\Models\User;
use Tests\TestCase;

class PersonalDataExportTest extends TestCase
{
    use RefreshDatabase;

    private function patientUser(): array
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Maria']);
        $user->assignRole('paciente');
        $patient = Patient::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'display_name' => 'Maria',
            'email' => $user->email,
            'phones_encrypted' => ['11999990000'],
        ]);

        return [$user, $patient];
    }

    public function test_patient_can_view_own_data(): void
    {
        [$user] = $this->patientUser();

        $this->actingAs($user)->get('/lgpd/meus-dados')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Lgpd/MyData')
                ->where('data.conta.nome', 'Maria')
                ->where('data.perfil.telefones', ['11999990000']));
    }

    public function test_download_returns_json_and_is_audited(): void
    {
        [$user] = $this->patientUser();

        $response = $this->actingAs($user)->get('/lgpd/meus-dados/download');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/json');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'lgpd.data_exported',
            'actor_user_id' => $user->id,
        ]);
    }
}

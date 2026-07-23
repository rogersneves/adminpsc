<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tenant\Models\Tenant;
use Modules\Users\Models\User;
use Tests\TestCase;

/**
 * Só confirma que o endpoint responde com o content-type certo — a lógica de dados
 * já é coberta pelos testes das Actions (tests/Unit/Reports). Não parseia o binário
 * do PDF/Excel gerado.
 */
class ExportsSmokeTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->create(['tenant_id' => $tenant->id]);
        $admin->assignRole('admin_clinica');

        return $admin;
    }

    public function test_sessions_pdf_and_excel_exports(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->get('/relatorios/sessoes/pdf')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($admin)->get('/relatorios/sessoes/excel')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_financial_pdf_and_excel_exports(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->get('/relatorios/financeiro/pdf')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($admin)->get('/relatorios/financeiro/excel')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_attendance_pdf_and_excel_exports(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->get('/relatorios/comparecimento/pdf')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($admin)->get('/relatorios/comparecimento/excel')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }
}

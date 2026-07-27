<?php

declare(strict_types=1);

namespace Tests\Feature\CMS;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\CMS\Enums\PageStatus;
use Modules\CMS\Models\Page;
use Modules\Tenant\Models\Tenant;
use Modules\Users\Models\User;
use Tests\TestCase;

/**
 * `CurrentTenant::ownsOrFail` no PageController é a única forma desta checagem ser
 * exercitável no PHPUnit (a global scope é ignorada em console/testes — ver gotcha
 * Fase 2 no CLAUDE.md). Admin do tenant A recebendo uma Page do tenant B por binding
 * deve tomar 404, não editar/apagar o recurso alheio.
 */
class CmsTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function pageForNewTenant(): Page
    {
        $tenant = Tenant::factory()->create();

        return Page::query()->create([
            'tenant_id' => $tenant->id, 'title' => 'B', 'slug' => 'b',
            'status' => PageStatus::Published, 'is_home' => false, 'published_at' => now(),
        ]);
    }

    public function test_admin_cannot_edit_page_of_another_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $adminA = User::factory()->create(['tenant_id' => $tenantA->id]);
        $adminA->assignRole('admin_clinica');

        $pageB = $this->pageForNewTenant();

        $this->actingAs($adminA)->get("/cms/paginas/{$pageB->id}/editar")->assertNotFound();

        $this->actingAs($adminA)->put("/cms/paginas/{$pageB->id}", [
            'title' => 'hack', 'status' => 'publicada', 'is_home' => false, 'html' => '', 'css' => '',
        ])->assertNotFound();

        $this->assertSame('B', $pageB->fresh()->title);
    }

    public function test_admin_cannot_delete_page_of_another_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $adminA = User::factory()->create(['tenant_id' => $tenantA->id]);
        $adminA->assignRole('admin_clinica');

        $pageB = $this->pageForNewTenant();

        $this->actingAs($adminA)->delete("/cms/paginas/{$pageB->id}")->assertNotFound();

        $this->assertDatabaseHas('cms_pages', ['id' => $pageB->id, 'deleted_at' => null]);
    }
}

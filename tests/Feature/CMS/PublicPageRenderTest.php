<?php

declare(strict_types=1);

namespace Tests\Feature\CMS;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\CMS\Enums\PageStatus;
use Modules\CMS\Models\Page;
use Modules\Tenant\Models\Tenant;
use Tests\TestCase;

class PublicPageRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_home_page_renders_for_guests(): void
    {
        $tenant = Tenant::factory()->create();
        Page::query()->create([
            'tenant_id' => $tenant->id, 'title' => 'Início', 'slug' => 'inicio',
            'status' => PageStatus::Published, 'is_home' => true, 'published_at' => now(),
            'html' => '<h1>Bem-vindo</h1>', 'css' => 'h1{color:blue}',
        ]);

        $response = $this->get("/c/{$tenant->slug}");

        $response->assertOk();
        $response->assertSee('Bem-vindo');
        $response->assertSee('color:blue', false);
    }

    public function test_published_page_renders_by_slug(): void
    {
        $tenant = Tenant::factory()->create();
        Page::query()->create([
            'tenant_id' => $tenant->id, 'title' => 'Sobre', 'slug' => 'sobre',
            'status' => PageStatus::Published, 'is_home' => false, 'published_at' => now(),
            'html' => '<p>Nossa história</p>', 'css' => '',
        ]);

        $this->get("/c/{$tenant->slug}/p/sobre")
            ->assertOk()
            ->assertSee('Nossa história');
    }

    public function test_draft_page_is_not_public(): void
    {
        $tenant = Tenant::factory()->create();
        Page::query()->create([
            'tenant_id' => $tenant->id, 'title' => 'Rascunho', 'slug' => 'rascunho',
            'status' => PageStatus::Draft, 'is_home' => false, 'html' => '<p>secreto</p>',
        ]);

        $this->get("/c/{$tenant->slug}/p/rascunho")->assertNotFound();
    }

    public function test_missing_home_page_returns_404(): void
    {
        $tenant = Tenant::factory()->create();

        $this->get("/c/{$tenant->slug}")->assertNotFound();
    }

    public function test_inactive_tenant_returns_404(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'suspended']);
        Page::query()->create([
            'tenant_id' => $tenant->id, 'title' => 'Início', 'slug' => 'inicio',
            'status' => PageStatus::Published, 'is_home' => true, 'published_at' => now(), 'html' => '<h1>Oi</h1>',
        ]);

        $this->get("/c/{$tenant->slug}")->assertNotFound();
    }

    public function test_page_of_one_tenant_is_not_served_under_another_tenant_slug(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        Page::query()->create([
            'tenant_id' => $tenantA->id, 'title' => 'Sobre A', 'slug' => 'sobre',
            'status' => PageStatus::Published, 'is_home' => false, 'published_at' => now(), 'html' => '<p>A</p>',
        ]);

        // Mesmo slug "sobre", mas sob o slug do tenant B: não existe lá.
        $this->get("/c/{$tenantB->slug}/p/sobre")->assertNotFound();
    }
}

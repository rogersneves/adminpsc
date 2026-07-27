<?php

declare(strict_types=1);

namespace Tests\Feature\CMS;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\CMS\Enums\PageStatus;
use Modules\CMS\Models\Page;
use Modules\Tenant\Models\Tenant;
use Modules\Users\Models\User;
use Tests\TestCase;

class CMSPageManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(Tenant $tenant): User
    {
        $admin = User::factory()->create(['tenant_id' => $tenant->id]);
        $admin->assignRole('admin_clinica');

        return $admin;
    }

    public function test_admin_can_create_a_page_with_auto_slug_from_title(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = $this->admin($tenant);

        $response = $this->actingAs($admin)->post('/cms/paginas', [
            'title' => 'Sobre a Clínica',
            'slug' => null,
            'status' => 'rascunho',
            'is_home' => false,
            'html' => '<h1>Sobre</h1>',
            'css' => 'h1{color:#111}',
            'project_data' => ['pages' => []],
        ]);

        $response->assertRedirect();

        $page = Page::query()->withoutTenantScope()->where('tenant_id', $tenant->id)->first();
        $this->assertNotNull($page);
        $this->assertSame('sobre-a-clinica', $page->slug);
        $this->assertSame(PageStatus::Draft, $page->status);
        $this->assertNull($page->published_at);
    }

    public function test_publishing_stamps_published_at(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = $this->admin($tenant);

        $this->actingAs($admin)->post('/cms/paginas', [
            'title' => 'Início',
            'status' => 'publicada',
            'is_home' => true,
            'html' => '<h1>Olá</h1>',
            'css' => '',
        ])->assertRedirect();

        $page = Page::query()->withoutTenantScope()->where('tenant_id', $tenant->id)->first();
        $this->assertSame(PageStatus::Published, $page->status);
        $this->assertNotNull($page->published_at);
    }

    public function test_only_one_home_page_per_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = $this->admin($tenant);

        $first = Page::query()->create([
            'tenant_id' => $tenant->id, 'title' => 'A', 'slug' => 'a',
            'status' => PageStatus::Published, 'is_home' => true, 'published_at' => now(),
        ]);

        $second = Page::query()->create([
            'tenant_id' => $tenant->id, 'title' => 'B', 'slug' => 'b',
            'status' => PageStatus::Published, 'is_home' => false,
        ]);

        $this->actingAs($admin)->put("/cms/paginas/{$second->id}", [
            'title' => 'B', 'slug' => 'b', 'status' => 'publicada', 'is_home' => true, 'html' => '', 'css' => '',
        ])->assertRedirect();

        $this->assertFalse($first->fresh()->is_home);
        $this->assertTrue($second->fresh()->is_home);
    }

    public function test_admin_can_delete_a_page(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = $this->admin($tenant);

        $page = Page::query()->create([
            'tenant_id' => $tenant->id, 'title' => 'X', 'slug' => 'x', 'status' => PageStatus::Draft, 'is_home' => false,
        ]);

        $this->actingAs($admin)->delete("/cms/paginas/{$page->id}")->assertRedirect();

        $this->assertSoftDeleted('cms_pages', ['id' => $page->id]);
    }

    public function test_html_is_sanitized_on_save(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = $this->admin($tenant);

        $this->actingAs($admin)->post('/cms/paginas', [
            'title' => 'XSS',
            'status' => 'publicada',
            'is_home' => false,
            'html' => '<div>ok</div><script>alert(1)</script><a href="javascript:alert(1)" onclick="x()">l</a>',
            'css' => '@import url(evil);body{color:red}',
        ])->assertRedirect();

        $page = Page::query()->withoutTenantScope()->where('tenant_id', $tenant->id)->first();
        $this->assertStringNotContainsString('<script', $page->html);
        $this->assertStringNotContainsString('javascript:', $page->html);
        $this->assertStringNotContainsString('onclick', $page->html);
        $this->assertStringContainsString('<div>ok</div>', $page->html);
        $this->assertStringNotContainsString('@import', $page->css);
        $this->assertStringContainsString('color:red', $page->css);
    }

    public function test_user_without_manage_cms_permission_is_forbidden(): void
    {
        $tenant = Tenant::factory()->create();
        $psychologist = User::factory()->create(['tenant_id' => $tenant->id]);
        $psychologist->assignRole('psicologo');

        $this->actingAs($psychologist)->get('/cms/paginas')->assertForbidden();

        $this->actingAs($psychologist)->post('/cms/paginas', [
            'title' => 'Nope', 'status' => 'rascunho', 'is_home' => false,
        ])->assertForbidden();
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Lgpd;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Security\Actions\Lgpd\PublishLegalDocumentAction;
use Modules\Security\Enums\LegalDocumentType;
use Modules\Security\Models\LegalDocument;
use Modules\Tenant\Models\Tenant;
use Modules\Users\Models\User;
use Tests\TestCase;

class LegalDocumentPublishingTest extends TestCase
{
    use RefreshDatabase;

    private function admin(Tenant $tenant): User
    {
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole('admin_clinica');

        return $user;
    }

    public function test_admin_can_publish_the_first_version(): void
    {
        $tenant = Tenant::factory()->create();

        $this->actingAs($this->admin($tenant))->post('/lgpd/documentos', [
            'type' => LegalDocumentType::PrivacyPolicy->value,
            'title' => 'Política v1',
            'content' => 'Conteúdo v1',
        ])->assertRedirect();

        $doc = LegalDocument::query()->withoutTenantScope()->first();
        $this->assertSame(1, $doc->version);
        $this->assertTrue($doc->is_current);
    }

    public function test_publishing_again_supersedes_the_current_version(): void
    {
        // Via Action direta: pelo HTTP, publicar a v1 já bloqueia o próprio admin no
        // gating de consentimento (comportamento correto), o que atrapalharia o 2º post.
        $tenant = Tenant::factory()->create();
        $publish = app(PublishLegalDocumentAction::class);

        $publish($tenant, LegalDocumentType::PrivacyPolicy, 'v1', 'c1');
        $publish($tenant, LegalDocumentType::PrivacyPolicy, 'v2', 'c2');

        $docs = LegalDocument::query()->withoutTenantScope()
            ->where('type', LegalDocumentType::PrivacyPolicy->value)->orderBy('version')->get();

        $this->assertCount(2, $docs);
        $this->assertFalse($docs[0]->is_current);
        $this->assertTrue($docs[1]->is_current);
        $this->assertSame(2, $docs[1]->version);
    }

    public function test_publishing_is_audited(): void
    {
        $tenant = Tenant::factory()->create();

        $this->actingAs($this->admin($tenant))->post('/lgpd/documentos', [
            'type' => LegalDocumentType::TermsOfUse->value, 'title' => 'Termos', 'content' => 'c',
        ]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'lgpd.legal_document_published']);
    }

    public function test_user_without_manage_legal_is_forbidden(): void
    {
        $tenant = Tenant::factory()->create();
        $psychologist = User::factory()->create(['tenant_id' => $tenant->id]);
        $psychologist->assignRole('psicologo');

        $this->actingAs($psychologist)->get('/lgpd/documentos')->assertForbidden();
        $this->actingAs($psychologist)->post('/lgpd/documentos', [
            'type' => LegalDocumentType::PrivacyPolicy->value, 'title' => 'x', 'content' => 'y',
        ])->assertForbidden();
    }
}

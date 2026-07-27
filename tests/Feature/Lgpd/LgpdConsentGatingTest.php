<?php

declare(strict_types=1);

namespace Tests\Feature\Lgpd;

use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Modules\Security\Enums\LegalDocumentType;
use Modules\Security\Models\Consent;
use Modules\Security\Models\LegalDocument;
use Modules\Tenant\Models\Tenant;
use Modules\Users\Models\User;
use Tests\TestCase;

class LgpdConsentGatingTest extends TestCase
{
    use RefreshDatabase;

    private function userWithTenant(): array
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole('admin_clinica');

        return [$tenant, $user];
    }

    private function currentDoc(Tenant $tenant, int $version = 1): LegalDocument
    {
        return LegalDocument::query()->create([
            'tenant_id' => $tenant->id,
            'type' => LegalDocumentType::PrivacyPolicy,
            'version' => $version,
            'title' => 'Política de Privacidade',
            'content' => 'Texto da política.',
            'is_current' => true,
            'published_at' => now(),
        ]);
    }

    public function test_no_gating_when_tenant_has_no_current_document(): void
    {
        [, $user] = $this->userWithTenant();

        $this->actingAs($user)->get('/dashboard')->assertOk();
    }

    public function test_user_is_redirected_to_consent_when_a_document_is_unaccepted(): void
    {
        [$tenant, $user] = $this->userWithTenant();
        $this->currentDoc($tenant);

        $this->actingAs($user)->get('/dashboard')->assertRedirect('/lgpd/consentimento');
    }

    public function test_accepting_records_consent_and_unblocks_access(): void
    {
        [$tenant, $user] = $this->userWithTenant();
        $doc = $this->currentDoc($tenant);

        $this->actingAs($user)->post('/lgpd/consentimento', ['accept' => true])->assertRedirect();

        $this->assertDatabaseHas('lgpd_consents', [
            'user_id' => $user->id,
            'document_type' => LegalDocumentType::PrivacyPolicy->value,
            'document_version' => $doc->version,
        ]);

        $this->actingAs($user)->get('/dashboard')->assertOk();
    }

    public function test_refusing_keeps_the_user_blocked(): void
    {
        [$tenant, $user] = $this->userWithTenant();
        $this->currentDoc($tenant);

        $this->actingAs($user)->post('/lgpd/consentimento', ['accept' => false])->assertSessionHasErrors('accept');
        $this->assertSame(0, Consent::query()->count());
    }

    public function test_publishing_a_new_version_requires_reconsent(): void
    {
        [$tenant, $user] = $this->userWithTenant();
        $v1 = $this->currentDoc($tenant, 1);

        // Aceita a v1.
        $this->actingAs($user)->post('/lgpd/consentimento', ['accept' => true]);
        $this->actingAs($user)->get('/dashboard')->assertOk();

        // Nova versão publicada: v1 deixa de ser atual, v2 vira atual.
        $v1->update(['is_current' => false]);
        $this->currentDoc($tenant, 2);

        $this->actingAs($user)->get('/dashboard')->assertRedirect('/lgpd/consentimento');
    }

    public function test_consent_is_append_only(): void
    {
        [$tenant, $user] = $this->userWithTenant();
        $doc = $this->currentDoc($tenant);
        $this->actingAs($user)->post('/lgpd/consentimento', ['accept' => true]);

        $consent = Consent::query()->first();

        $this->expectException(LogicException::class);
        $consent->update(['document_version' => 99]);
    }
}

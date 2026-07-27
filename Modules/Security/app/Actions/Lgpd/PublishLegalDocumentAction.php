<?php

declare(strict_types=1);

namespace Modules\Security\Actions\Lgpd;

use Illuminate\Support\Facades\DB;
use Modules\Security\Enums\LegalDocumentType;
use Modules\Security\Models\LegalDocument;
use Modules\Tenant\Models\Tenant;

/**
 * Publica uma nova versão de um documento legal do tenant. Nunca sobrescreve: cria a
 * próxima versão como `is_current` e aposenta a anterior (histórico preservado). Como
 * a versão atual muda, os aceites deixam de cobrir a versão nova — o EnsureLgpdConsent
 * passa a exigir re-aceite automaticamente.
 */
class PublishLegalDocumentAction
{
    public function __invoke(Tenant $tenant, LegalDocumentType $type, string $title, string $content): LegalDocument
    {
        return DB::transaction(function () use ($tenant, $type, $title, $content): LegalDocument {
            $maxVersion = (int) LegalDocument::query()
                ->withoutTenantScope()
                ->where('tenant_id', $tenant->id)
                ->where('type', $type->value)
                ->lockForUpdate()
                ->max('version');

            LegalDocument::query()
                ->withoutTenantScope()
                ->where('tenant_id', $tenant->id)
                ->where('type', $type->value)
                ->where('is_current', true)
                ->update(['is_current' => false]);

            return LegalDocument::query()->create([
                'tenant_id' => $tenant->id,
                'type' => $type,
                'version' => $maxVersion + 1,
                'title' => $title,
                'content' => $content,
                'is_current' => true,
                'published_at' => now(),
            ]);
        });
    }
}

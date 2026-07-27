<?php

declare(strict_types=1);

namespace Modules\Security\Services;

use Modules\Security\Enums\LegalDocumentType;
use Modules\Security\Models\Consent;
use Modules\Security\Models\LegalDocument;
use Modules\Users\Models\User;

/**
 * Decide quais documentos legais o usuário ainda precisa aceitar. Fonte da verdade
 * para o EnsureLgpdConsent (gating) e para a tela de aceite. Se o tenant não publicou
 * nenhum documento atual, não há nada a consentir (recurso opt-in por clínica —
 * não força consentimento sobre o vazio, não quebra tenants que ainda não configuraram).
 */
class ConsentChecker
{
    /**
     * Documentos com versão atual que este usuário ainda não aceitou.
     *
     * @return array<int, LegalDocument>
     */
    public function pendingFor(User $user): array
    {
        if ($user->tenant_id === null) {
            return []; // Super Admin (sem tenant) não tem documentos de clínica a aceitar.
        }

        $current = LegalDocument::query()
            ->withoutTenantScope()
            ->where('tenant_id', $user->tenant_id)
            ->where('is_current', true)
            ->whereIn('type', array_map(fn (LegalDocumentType $t) => $t->value, LegalDocumentType::required()))
            ->get();

        $pending = [];

        foreach ($current as $document) {
            if (! $this->hasAccepted($user, $document)) {
                $pending[] = $document;
            }
        }

        return $pending;
    }

    public function hasPending(User $user): bool
    {
        return $this->pendingFor($user) !== [];
    }

    private function hasAccepted(User $user, LegalDocument $document): bool
    {
        return Consent::query()
            ->where('user_id', $user->id)
            ->where('document_type', $document->type->value)
            ->where('document_version', $document->version)
            ->exists();
    }
}

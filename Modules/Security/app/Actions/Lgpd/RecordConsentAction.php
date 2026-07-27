<?php

declare(strict_types=1);

namespace Modules\Security\Actions\Lgpd;

use Illuminate\Support\Facades\Request;
use Modules\Audit\Services\AuditLogger;
use Modules\Security\Models\Consent;
use Modules\Security\Models\LegalDocument;
use Modules\Users\Models\User;

/**
 * Registra o aceite de uma versão de documento legal por um usuário. Append-only:
 * sempre cria uma linha nova (a prova histórica), capturando IP/User-Agent do request.
 * Auditado (docs/04-Seguranca.md exige registro da operação de consentimento).
 */
class RecordConsentAction
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(User $user, LegalDocument $document): Consent
    {
        $consent = Consent::query()->create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'legal_document_id' => $document->id,
            'document_type' => $document->type,
            'document_version' => $document->version,
            'ip_address' => Request::ip(),
            'user_agent' => Request::header('User-Agent'),
            'accepted_at' => now(),
        ]);

        $this->audit->record(
            'lgpd.consent_recorded',
            actor: $user,
            subject: $document,
            changes: ['document_type' => $document->type->value, 'version' => $document->version],
            tenantId: $user->tenant_id,
        );

        return $consent;
    }
}

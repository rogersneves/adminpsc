<?php

declare(strict_types=1);

namespace Modules\Security\Enums;

/**
 * Documentos legais que exigem aceite do titular (LGPD, docs/04-Seguranca.md).
 */
enum LegalDocumentType: string
{
    case PrivacyPolicy = 'privacy_policy';
    case TermsOfUse = 'terms_of_use';

    public function label(): string
    {
        return match ($this) {
            self::PrivacyPolicy => 'Política de Privacidade',
            self::TermsOfUse => 'Termos de Uso',
        };
    }

    /**
     * Documentos cujo aceite da versão atual é obrigatório para usar o sistema.
     *
     * @return list<self>
     */
    public static function required(): array
    {
        return [self::PrivacyPolicy, self::TermsOfUse];
    }
}

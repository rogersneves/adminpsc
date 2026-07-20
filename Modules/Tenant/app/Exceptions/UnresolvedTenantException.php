<?php

declare(strict_types=1);

namespace Modules\Tenant\Exceptions;

use RuntimeException;

/**
 * Lançada quando um Model com BelongsToTenant é consultado fora de um contexto HTTP
 * com tenant resolvido. Falha segura: nunca deixar uma query de negócio rodar sem
 * filtro de tenant "por omissão".
 */
class UnresolvedTenantException extends RuntimeException
{
    public function __construct(string $modelClass)
    {
        parent::__construct(
            "Não é possível consultar [{$modelClass}] sem um tenant resolvido no contexto atual."
        );
    }
}

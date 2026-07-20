<?php

declare(strict_types=1);

namespace Modules\Tenant\Support;

use Modules\Tenant\Models\Tenant;

/**
 * Contexto de tenant da requisição atual. Registrado como singleton no container
 * e populado pelo middleware ResolveTenant depois que o usuário está autenticado.
 *
 * Fica deliberadamente não-resolvido (tenant null) para o Super Admin e para
 * requisições de convidado — módulos de negócio devem tratar isso como "sem
 * acesso a dado de tenant", nunca assumir um tenant implícito.
 */
class CurrentTenant
{
    private ?Tenant $tenant = null;

    private bool $resolved = false;

    public function set(?Tenant $tenant): void
    {
        $this->tenant = $tenant;
        $this->resolved = true;
    }

    public function get(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?string
    {
        return $this->tenant?->id;
    }

    public function resolved(): bool
    {
        return $this->resolved;
    }
}

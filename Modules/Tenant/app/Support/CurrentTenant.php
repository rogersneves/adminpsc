<?php

declare(strict_types=1);

namespace Modules\Tenant\Support;

use Illuminate\Database\Eloquent\Model;
use Modules\Tenant\Models\Tenant;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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

    /**
     * Checagem explícita extra para Models resolvidos via route-model-binding.
     *
     * `TenantScope` sozinho não é suficiente aqui: `app()->runningInConsole()` do
     * Laravel é sempre `true` durante testes PHPUnit (via `isRunningUnitTests()`),
     * então a global scope nunca é exercitada de verdade num teste de feature — um
     * binding de rota poderia silenciosamente resolver um registro de outro tenant
     * sem que nenhum teste pegasse isso. Chamar este método explicitamente em
     * qualquer Controller que receba um Model tenant-scoped via binding de rota
     * garante a proteção e a torna testável. Ver gotcha no CLAUDE.md.
     */
    public function ownsOrFail(Model $model): void
    {
        if ($model->getAttribute('tenant_id') !== $this->id()) {
            throw new NotFoundHttpException;
        }
    }
}

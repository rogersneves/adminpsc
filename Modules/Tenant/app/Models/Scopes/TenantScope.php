<?php

declare(strict_types=1);

namespace Modules\Tenant\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Modules\Tenant\Exceptions\UnresolvedTenantException;
use Modules\Tenant\Support\CurrentTenant;

/**
 * Isolamento multi-tenant por coluna (ADR-003 em docs/01-Arquitetura.md). Aplicado
 * pelo trait BelongsToTenant. Falha segura: fora do console (artisan/seeders/testes),
 * uma query sem tenant resolvido lança exceção em vez de silenciosamente devolver
 * dados de todos os tenants ou nenhum.
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $currentTenant = app(CurrentTenant::class);

        if (! $currentTenant->resolved()) {
            if (app()->runningInConsole()) {
                return;
            }

            throw new UnresolvedTenantException($model::class);
        }

        $builder->where($model->qualifyColumn('tenant_id'), $currentTenant->id());
    }

    public function extend(Builder $builder): void
    {
        $builder->macro('withoutTenantScope', function (Builder $builder) {
            return $builder->withoutGlobalScope(self::class);
        });
    }
}

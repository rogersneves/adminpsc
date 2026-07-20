<?php

declare(strict_types=1);

namespace Modules\Tenant\Traits;

use Modules\Tenant\Models\Scopes\TenantScope;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Support\CurrentTenant;

/**
 * Para Models de negócio (a partir da Fase 2: Patients, Sessions, etc). Preenche
 * tenant_id automaticamente na criação e aplica o isolamento via TenantScope.
 *
 * Não usar em Models de autenticação (User) — ver nota em docs/01-Arquitetura.md
 * sobre por que a tabela users não pode depender de um tenant já resolvido.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            if ($model->tenant_id === null) {
                $model->tenant_id = app(CurrentTenant::class)->id();
            }
        });
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}

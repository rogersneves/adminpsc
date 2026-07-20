<?php

declare(strict_types=1);

namespace Modules\Core\Traits;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * UUID v7 (ordenável por tempo) como chave primária, em vez de IDs sequenciais.
 * HasUuids já gera v7 por padrão nesta versão do Laravel (Str::uuid7()).
 *
 * @see docs/02-Banco-de-Dados.md
 */
trait HasUuidPrimaryKey
{
    use HasUuids;

    public function getRouteKeyName(): string
    {
        return $this->getKeyName();
    }
}

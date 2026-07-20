<?php

declare(strict_types=1);

namespace Modules\Audit\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;
use Modules\Core\Traits\HasUuidPrimaryKey;

/**
 * Append-only por design (docs/04-Seguranca.md): sem updated_at/deleted_at, e
 * update()/delete() lançam exceção — a imutabilidade é estrutural, não apenas
 * pela ausência de uma rota de edição.
 */
class AuditLog extends Model
{
    use HasUuidPrimaryKey;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'actor_user_id',
        'action',
        'auditable_type',
        'auditable_id',
        'changes',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function update(array $attributes = [], array $options = []): bool
    {
        throw new LogicException('audit_logs é append-only: registros não podem ser atualizados.');
    }

    public function delete(): bool
    {
        throw new LogicException('audit_logs é append-only: registros não podem ser excluídos.');
    }
}

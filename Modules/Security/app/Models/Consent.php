<?php

declare(strict_types=1);

namespace Modules\Security\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;
use Modules\Core\Traits\HasUuidPrimaryKey;
use Modules\Security\Enums\LegalDocumentType;
use Modules\Users\Models\User;

/**
 * Prova de aceite de um documento legal por um usuário (LGPD). Append-only, mesmo
 * padrão de AuditLog: sem updated_at, e update()/delete() lançam exceção — um aceite
 * é um fato histórico, não pode ser alterado nem apagado.
 *
 * Não usa BelongsToTenant (o isolamento vem da relação com o User, mesmo raciocínio
 * de AuditLog/notifications); guarda tenant_id só para consulta/relatório.
 */
class Consent extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'lgpd_consents';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'legal_document_id',
        'document_type',
        'document_version',
        'ip_address',
        'user_agent',
        'accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'document_type' => LegalDocumentType::class,
            'document_version' => 'integer',
            'accepted_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function update(array $attributes = [], array $options = []): bool
    {
        throw new LogicException('lgpd_consents é append-only: aceites não podem ser atualizados.');
    }

    public function delete(): bool
    {
        throw new LogicException('lgpd_consents é append-only: aceites não podem ser excluídos.');
    }
}

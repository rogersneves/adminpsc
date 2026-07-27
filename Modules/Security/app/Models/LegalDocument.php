<?php

declare(strict_types=1);

namespace Modules\Security\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Traits\HasUuidPrimaryKey;
use Modules\Security\Enums\LegalDocumentType;
use Modules\Tenant\Traits\BelongsToTenant;

/**
 * Uma versão publicada de um documento legal de um tenant. Nunca editado in place —
 * publicar uma nova versão cria outra linha e move o `is_current` (ver
 * PublishLegalDocumentAction). O conteúdo não é cifrado: é um texto que o tenant
 * exibe publicamente aos titulares.
 */
class LegalDocument extends Model
{
    use BelongsToTenant, HasUuidPrimaryKey;

    protected $fillable = [
        'tenant_id',
        'type',
        'version',
        'title',
        'content',
        'is_current',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => LegalDocumentType::class,
            'is_current' => 'boolean',
            'published_at' => 'datetime',
        ];
    }
}

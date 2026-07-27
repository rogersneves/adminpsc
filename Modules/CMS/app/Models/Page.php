<?php

declare(strict_types=1);

namespace Modules\CMS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\CMS\Enums\PageStatus;
use Modules\Core\Traits\HasUuidPrimaryKey;
use Modules\Tenant\Traits\BelongsToTenant;

/**
 * Página pública editável no CMS (Fase 8). Conteúdo não é cifrado — página pública
 * é pública por definição, não há PII em repouso aqui. `html`/`css` são os artefatos
 * já sanitizados servidos ao visitante; `project_data` é o estado do editor GrapesJS,
 * nunca exposto publicamente.
 */
class Page extends Model
{
    use BelongsToTenant, HasUuidPrimaryKey, SoftDeletes;

    protected $table = 'cms_pages';

    protected $fillable = [
        'tenant_id',
        'slug',
        'title',
        'status',
        'is_home',
        'html',
        'css',
        'project_data',
        'meta_title',
        'meta_description',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PageStatus::class,
            'is_home' => 'boolean',
            'project_data' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function isPublished(): bool
    {
        return $this->status === PageStatus::Published;
    }
}

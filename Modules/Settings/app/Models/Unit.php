<?php

declare(strict_types=1);

namespace Modules\Settings\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Traits\HasUuidPrimaryKey;
use Modules\Tenant\Traits\BelongsToTenant;
use Modules\Users\Models\User;

/**
 * Unidade (filial) de uma clínica. Estrutura organizacional do tenant — psicólogos e
 * secretárias são lotados aqui (pivot unit_user) e as sessões acontecem numa unidade.
 */
class Unit extends Model
{
    use BelongsToTenant, HasUuidPrimaryKey, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'city',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'unit_user');
    }
}

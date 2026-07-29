<?php

declare(strict_types=1);

namespace Modules\Financial\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Traits\HasUuidPrimaryKey;
use Modules\Tenant\Traits\BelongsToTenant;

/**
 * Convênio (plano de saúde) aceito pela clínica (marco: convênios). Catálogo por
 * tenant; pacientes e cobranças referenciam um convênio (nullable = particular).
 */
class HealthPlan extends Model
{
    use BelongsToTenant, HasUuidPrimaryKey, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
